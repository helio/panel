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

    private static $createManagerCommand = 'mco playbook run infrastructure::gce::create --input \'{"node":"%s","callback":"$jobCallback","user_id":"%s","id":"$jobId"}\'';
    private static $deleteManagerCommand = 'mco playbook run infrastructure::gce::delete --input \'{"node":"%s","callback":"$jobCallback","id":"$jobId"}\'';
    private static $inventoryCommand = 'mco playbook run helio::tools::inventory --input \'{"fqdn":"$fqdn","callback":"$instanceCallback"}\'';
    private static $startComputeCommand = 'mco playbook run helio::cluster::node::start --input \'{"node_id":"%s","node_fqdn":"$fqdn","manager":"%s","callback":"$instanceCallback"}\'';
    private static $stopComputeCommand = 'mco playbook run helio::cluster::node::stop --input \'{"node_id":"%s","node_fqdn":"$fqdn","manager":"%s","callback":"$instanceCallback"}\'';
    private static $removeNodeCommand = 'mco playbook run helio::cluster::node::cleanup --input \'{"node_id":"%s","node_fqdn":"$fqdn","manager":"%s","callback":"$instanceCallback"}\'';
    private static $inspectCommand = 'mco playbook run helio::cluster::node::inspect --input \'{"node_fqdn":"$fqdn","callback":"$instanceCallback"}\'';
    private static $getRunnerIdCommand = 'mco playbook run helio::cluster::node::getid --input \'{"node_fqdn":"$fqdn","callback":"$instanceCallback"}\'';
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

        $manager = $this->job->getManager();

        if (!ManagerStatus::isValidActiveStatus($manager->getStatus())) {
            LogHelper::err('dispatchJob called on job ' . $this->job->getId() . ' that\'s not ready. Aborting.');

            return false;
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
                    $manager->getIdByChoria(),
                ])
            );
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function provisionManager(): void
    {
        if (!$this->job) {
            throw new \InvalidArgumentException('job is required');
        }

        $manager = $this->job->getManager();
        if (!$manager) {
            throw new \Exception('This should not happen! Manager is required.');
        }

        // we're good
        if ($manager && $manager->works() && JobStatus::READY_PAUSED !== $this->job->getStatus()) {
            return;
        }

        // provision the manager
        ServerUtility::executeShellCommand($this->parseCommand(self::$createManagerCommand, false, [
            $manager->getName(),
            $this->job->getOwner() ? $this->job->getOwner()->getId() : null,
        ]));
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
            ServerUtility::executeShellCommand($this->parseCommand(self::$getRunnerIdCommand));
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
