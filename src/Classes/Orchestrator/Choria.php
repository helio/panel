<?php

namespace Helio\Panel\Orchestrator;

use \Exception;
use Helio\Panel\Utility\ArrayUtility;
use \RuntimeException;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Utility\ServerUtility;

class Choria implements OrchestratorInterface
{


    /**
     * @var Instance
     */
    protected $instance;


    /**
     * @var Job
     */
    protected $job;


    /**
     * @var string
     */
    private static $username = 'panel';


    /**
     * @var string
     */
    private static $managerPrefix = 'manager';

    /**
     * @var int
     */
    private static $redundancyCount = 0;


    private static $firstManagerCommand = 'mco playbook run infrastructure::gce::create --input \'{"node":"%s","callback":"$jobCallback","user_id":"%s","id":"$jobId"}\'';
    private static $redundantManagersCommand = 'mco playbook run infrastructure::gce::create --input \'{"node":["%s"],"master_token":"%s","callback":"$jobCallback","user_id":"%s","id":"$jobId"\'';
    private static $deleteInitManagerCommand = 'mco playbook run infrastructure::gce::delete --input \'{"node":"%s","callback":"$jobCallback","id":"$jobId"}\'';
    private static $deleteRedundantManagersCommand = 'mco playbook run infrastructure::gce::delete --input \'{"node":["%s"],"master_token":"%s","callback":"$jobCallback","id":"$jobId"}\'';
    private static $inventoryCommand = 'mco playbook run helio::tools::inventory --input \'{"fqdn":"$fqdn","callback":"$instanceCallback"}\'';
    private static $startComputeCommand = 'mco playbook run helio::cluster::node::start --input \'{"node_id":"%s","node_fqdn":"$fqdn","manager":"%s","callback":"$instanceCallback"}\'';
    private static $stopComputeCommand = 'mco playbook run helio::cluster::node::stop --input \'{"node_id":"%s","node_fqdn":"$fqdn","manager":"%s","callback":"$instanceCallback"}\'';
    private static $removeNodeCommand = 'mco playbook run helio::cluster::node::cleanup --input \'{"node_id":"%s","node_fqdn":"$fqdn","manager":"%s","callback":"$instanceCallback"}\'';
    private static $inspectCommand = 'mco playbook run helio::cluster::node::inspect --input \'{"node_fqdn":"$fqdn","callback":"$instanceCallback"}\'';
    private static $getRunnderIdCommand = 'mco playbook run helio::cluster::node::getid --input \'{"node_fqdn":"$fqdn","callback":"$instanceCallback"}\'';
    private static $dispatchCommand = 'mco playbook run helio::task::update --input \'{"cluster_address":"%s","task_ids":"[%s]"}\'';
    private static $joinWorkersCommand = 'mco playbook run helio::queue --input \'{"cluster_join_token":"%s","cluster_join_address":"%s","cluster_join_count":"%s"}\'';

    /**
     * Choria constructor.
     * @param Instance $instance
     * @param Job|null $job
     */
    public function __construct(Instance $instance, Job $job = null)
    {
        $this->instance = $instance;
        $this->job = $job;
    }

    /**
     * @return mixed
     */
    public function getInventory()
    {
        return ServerUtility::executeShellCommand($this->parseCommand(self::$inventoryCommand));
    }

    /**
     * @return mixed
     */
    public function startComputing()
    {
        return ServerUtility::executeShellCommand($this->parseCommand(self::$startComputeCommand));
    }


    /**
     * @return mixed
     */
    public function stopComputing()
    {
        return ServerUtility::executeShellCommand($this->parseCommand(self::$stopComputeCommand));
    }


    /**
     * @param Job|null $job
     * @return bool
     */
    public function dispatchJob(Job $job = null): bool
    {
        if ($job) {
            LogHelper::addWarning('Deprecated call of ' . __METHOD__ . ' with set $job param. Pass it to the factory!');
            $this->job = $job;
        }
        if (!$this->job) {
            return false;
        }
        if (!$this->job->getManagerNodes() || !$this->job->getClusterToken() || !$this->job->getInitManagerIp()) {
            LogHelper::warn('dispatchJob called on job that\'s not ready. Aborting.');
            return false;
        }

        $resultDispatch = ServerUtility::executeShellCommand($this->parseCommand(self::$dispatchCommand, false, [
            $this->job->getManagerNodes()[0],
            ArrayUtility::modelsToStringOfIds($this->job->getExecutions()->toArray())
        ]));

        $resultJoinWorkers = ServerUtility::executeShellCommand($this->parseCommand(self::$joinWorkersCommand, false, [$this->job->getClusterToken(), $this->job->getInitManagerIp(), 1]));

        return $resultDispatch && $resultJoinWorkers;
    }


    /**
     * @param Job|null $job
     * @return bool
     * @throws Exception
     */
    public function provisionManager(Job $job = null): bool
    {
        if ($job) {
            LogHelper::addWarning('Deprecated call of ' . __METHOD__ . ' with set $job param. Pass it to the factory!');
            $this->job = $job;
        }
        if (!$this->job) {
            return false;
        }
        $managerHash = ServerUtility::getShortHashOfString($this->job->getId());

        // we're good
        if (count($this->job->getManagerNodes()) === (1 + self::$redundancyCount)) {
            return true;
        }

        // we have to provision the init manager
        if (count($this->job->getManagerNodes()) === 0) {
            $managerHostname = self::$managerPrefix . "-init-${managerHash}";

            $command = self::$firstManagerCommand;
            $params[] = $managerHostname;
        } else {

            // if no init manager exists, we cannot create redundancy managers
            if (!$this->job->getInitManagerIp()) {
                return false;
            }

            $redundancyNodes = [];

            $i = self::$redundancyCount + 1;
            while ($i <= self::$redundancyCount) {
                $redundancyNodes[] = self::$managerPrefix . "-redundancy-${managerHash}-${i}";
                $i--;
            }

            $command = self::$redundantManagersCommand;
            $params[] = implode('\\",\\"', $redundancyNodes);
            $params[] = $this->job->getManagerToken();
        }

        $params[] = $this->job->getOwner() ? $this->job->getOwner()->getId() : null;

        $result = ServerUtility::executeShellCommand($this->parseCommand($command, false, $params));
        return $result;
    }


    /**
     * @param Job|null $job
     * @return bool
     * @throws Exception
     */
    public function removeManager(Job $job = null): bool
    {
        if ($job) {
            LogHelper::addWarning('Deprecated call of ' . __METHOD__ . ' with set $job param. Pass it to the factory!');
            $this->job = $job;
        }
        if (!$this->job) {
            return false;
        }
        $result = true;
        $managerHash = ServerUtility::getShortHashOfString($this->job->getId());

        // remove redundant managers if existing
        if (count($this->job->getManagerNodes()) > 1) {
            foreach ($this->job->getManagerNodes() as $node) {
                if (strpos($node, self::$managerPrefix . "-redundancy-${managerHash}") === 0) {
                    $result = $result && ServerUtility::executeShellCommand($this->parseCommand(self::$deleteRedundantManagersCommand, true, [
                            substr($node, 0, strlen(self::$managerPrefix . "-init-${managerHash}-1")),
                            $this->job->getManagerToken()
                        ]));
                }
            }
        }

        // lastly, delete redundant manager
        $result = $result && ServerUtility::executeShellCommand($this->parseCommand(self::$deleteInitManagerCommand, false, [
                self::$managerPrefix . "-init-${managerHash}"
            ]));
        return $result;
    }


    /**
     * @return string|null
     */
    public function inspect()
    {
        return ServerUtility::executeShellCommand($this->parseCommand(self::$inspectCommand, true));
    }


    /**
     * @return string|null
     */
    public function removeInstance()
    {
        $this->ensureRunnerIdIsSet();
        return ServerUtility::executeShellCommand($this->parseCommand(self::$removeNodeCommand, false, [$this->instance->getRunnerId()]));
    }


    /**
     *
     */
    protected function ensureRunnerIdIsSet(): void
    {
        if (!$this->instance->getRunnerId()) {
            ServerUtility::executeShellCommand($this->parseCommand(self::$getRunnderIdCommand));
            throw new RuntimeException('Instance ID not set');
        }
    }


    /**
     * @param string $command
     * @param bool $waitForResult
     * @param array $parameter
     * @return string
     */
    protected function parseCommand(string $command, bool $waitForResult = false, array $parameter = []): string
    {
        $params = array_merge([
            self::$username, $this->instance->getOrchestratorCoordinator()
        ],
            $parameter
        );
        ServerUtility::validateParams($params);

        $command = str_replace(
            [
                '"',
                '$fqdn',
                '$instanceCallback',
                '$jobCallback',
                '$jobId',
                '$instanceId'
            ],
            [
                '\\"',
                $this->instance->getFqdn(),
                ServerUtility::getBaseUrl() . 'api/instance/callback?instanceid=' . $this->instance->getId(),
                ServerUtility::getBaseUrl() . 'api/job/callback?jobid=' . $this->job->getId(),
                $this->job->getId(),
                $this->instance->getId()
            ],
            $command
        );

        return vsprintf('ssh %s@%s "' . $command . '"' . ($waitForResult ? '' : ' > /dev/null 2>&1 &'), $params);
    }
}