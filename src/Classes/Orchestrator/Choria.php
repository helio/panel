<?php

namespace Helio\Panel\Orchestrator;

use Exception;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Manager;
use Helio\Panel\Utility\ArrayUtility;
use RuntimeException;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
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
    private static $managerPrefix = 'manager-init';

    private static $createManagerCommand = 'mco playbook run infrastructure::gce::create --input \'{"node":"%s","callback":"$jobCallback","user_id":"%s","id":"$jobId"}\'';
    private static $deleteManagerCommand = 'mco playbook run infrastructure::gce::delete --input \'{"node":"%s","callback":"$jobCallback","id":"$jobId"}\'';
    private static $inventoryCommand = 'mco playbook run helio::tools::inventory --input \'{"fqdn":"$fqdn","callback":"$instanceCallback"}\'';
    private static $startComputeCommand = 'mco playbook run helio::cluster::node::start --input \'{"node_id":"%s","node_fqdn":"$fqdn","manager":"%s","callback":"$instanceCallback"}\'';
    private static $stopComputeCommand = 'mco playbook run helio::cluster::node::stop --input \'{"node_id":"%s","node_fqdn":"$fqdn","manager":"%s","callback":"$instanceCallback"}\'';
    private static $removeNodeCommand = 'mco playbook run helio::cluster::node::cleanup --input \'{"node_id":"%s","node_fqdn":"$fqdn","manager":"%s","callback":"$instanceCallback"}\'';
    private static $inspectCommand = 'mco playbook run helio::cluster::node::inspect --input \'{"node_fqdn":"$fqdn","callback":"$instanceCallback"}\'';
    private static $getRunnderIdCommand = 'mco playbook run helio::cluster::node::getid --input \'{"node_fqdn":"$fqdn","callback":"$instanceCallback"}\'';
    private static $dispatchCommand = 'mco playbook run helio::task::update --input \'{"cluster_address":"%s","task_ids":"[%s]"}\'';
    private static $joinWorkersCommand = 'mco playbook run helio::queue --input \'{"cluster_join_token":"%s","cluster_join_address":"%s","cluster_join_count":"%s","manager_id":"%s"}\'';

    /**
     * Choria constructor.
     *
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
     * @return bool
     */
    public function dispatchJob(): bool
    {
        if (!$this->job) {
            return false;
        }

        if (!($manager = $this->job->getManager()) && (!$this->job->getManagerNodes() || !$this->job->getClusterToken() || !$this->job->getInitManagerIp())) {
            LogHelper::warn('dispatchJob called on job ' . $this->job->getId() . ' that\'s not ready. Aborting.');

            return false;
        }
        if (!$manager) {
            LogHelper::debug('Deprecated Job ' . $this->job->getId() . ' updated to new manager persistance model');
            $manager = (new Manager())
                ->setFqdn($this->job->getManagerNodes()[0])
                ->setWorkerToken($this->job->getClusterToken())
                ->setIp($this->job->getInitManagerIp())
                ->setManagerToken($this->job->getManagerToken())
                ->setIdByChoria($this->job->getManagerID())
                ->setStatus(ManagerStatus::READY);
            $this->job->setManager($manager)
                ->setManagerToken('')
                ->setClusterToken('')
                ->setInitManagerIp('')
                ->setManagerID('')
                ->setManagerNodes([]);
        }

        ServerUtility::executeShellCommand($this->parseCommand(self::$dispatchCommand, false, [
            $manager->getFqdn(),
            ArrayUtility::modelsToStringOfIds($this->job->getExecutions()->toArray()),
        ]));

        if ($this->job->getActiveExecutionCount() > 0) {
            ServerUtility::executeShellCommand(
                $this->parseCommand(self::$joinWorkersCommand, false, [
                    $manager->getWorkerToken(),
                    $manager->getIp(),
                    1,
                    $manager->getId(),
                ])
            );
        }

        return true;
    }

    /**
     * @param  string $managerName
     * @return string expected hostname of the new manager
     *
     * @throws Exception
     */
    public function provisionManager(string $managerName = ''): string
    {
        if (!$this->job) {
            throw new \InvalidArgumentException('job is required');
        }

        // we're good
        if ($this->job->getManager() && $this->job->getManager()->works() && JobStatus::READY_PAUSED !== $this->job->getStatus()) {
            return $this->job->getManager()->getName();
        }

        // TODO CB: Remove this once all active jobs switched to the normalised manager persistence model
        if (!$this->job->getManager() && count($this->job->getManagerNodes())) {
            $fqdn = $this->job->getManagerNodes()[0];
            $this->job->setManager(
                (new Manager())
                    ->setName(explode('.', $fqdn)[0])
                    ->setFqdn($fqdn)
                    ->setIp($this->job->getInitManagerIp())
                    ->setIdByChoria($this->job->getManagerID())
                    ->setManagerToken($this->job->getManagerToken())
                    ->setWorkerToken($this->job->getClusterToken())
                    ->setStatus(ManagerStatus::READY)
            )
                ->setManagerToken('')
                ->setClusterToken('')
                ->setInitManagerIp('')
                ->setManagerID('')
                ->setManagerNodes([]);
        }

        $managerName = self::$managerPrefix . '-' . ($managerName ?: strtolower(ServerUtility::getRandomString(4)));

        // provision the manager
        ServerUtility::executeShellCommand($this->parseCommand(self::$createManagerCommand, false, [
            $managerName,
            $this->job->getOwner() ? $this->job->getOwner()->getId() : null,
        ]));

        return $managerName;
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    public function removeManager(): bool
    {
        if (!$this->job) {
            return false;
        }

        if (!$this->job->getManager()) {
            return false;
        }

        /** @var Job $job */
        foreach ($this->job->getManager()->getJobs() as $job) {
            if ($job === $this->job || $job->getId() === $this->job->getId()) {
                continue;
            }
            // if there are other jobs than the current one, don't remove the manager.
            return false;
        }

        /* @var Manager $manager */
        return ServerUtility::executeShellCommand($this->parseCommand(self::$deleteManagerCommand, false, [$this->job->getManager()->getName()]));
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

    protected function ensureRunnerIdIsSet(): void
    {
        if (!$this->instance->getRunnerId()) {
            ServerUtility::executeShellCommand($this->parseCommand(self::$getRunnderIdCommand));
            throw new RuntimeException('Instance ID not set');
        }
    }

    /**
     * @param string $command
     * @param bool   $waitForResult
     * @param array  $parameter
     *
     * @return string
     */
    protected function parseCommand(string $command, bool $waitForResult = false, array $parameter = []): string
    {
        $params = array_merge(
            [
                self::$username, $this->instance->getOrchestratorCoordinator(),
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
                '$instanceId',
            ],
            [
                '\\"',
                $this->instance->getFqdn(),
                ServerUtility::getBaseUrl() . 'api/instance/callback?instanceid=' . $this->instance->getId(),
                ServerUtility::getBaseUrl() . 'api/job/callback?jobid=' . $this->job->getId(),
                $this->job->getId(),
                $this->instance->getId(),
            ],
            $command
        );

        return vsprintf('ssh %s@%s "' . $command . '"' . ($waitForResult ? '' : ' > /dev/null 2>&1 &'), $params);
    }
}
