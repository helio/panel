<?php

namespace Helio\Panel\Orchestrator;

use Exception;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Execution;
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

    private static $createManagerCommand = 'mco playbook run infrastructure::gce::create --input \'{"node":"%s","callback":"{{jobCallback}}","user_id":"%s","id":"{{jobId}}"}\'';
    private static $deleteManagerCommand = 'mco playbook run infrastructure::gce::delete --input \'{"node":"%s","fqdn":"%s","callback":"{{jobCallback}}","id":"{{jobId}}"}\'';
    private static $inventoryCommand = 'mco playbook run helio::tools::inventory --input \'{"fqdn":"{{fqdn}}","callback":"{{instanceCallback}}"}\'';
    private static $startComputeCommand = 'mco playbook run helio::cluster::node::start --input \'{"node_id":"%s","node_fqdn":"{{fqdn}}","manager":"%s","callback":"{{instanceCallback}}"}\'';
    private static $stopComputeCommand = 'mco playbook run helio::cluster::node::stop --input \'{"node_id":"%s","node_fqdn":"{{fqdn}}","manager":"%s","callback":"{{instanceCallback}}"}\'';
    private static $removeNodeCommand = 'mco playbook run helio::cluster::node::cleanup --input \'{"node_id":"%s","node_fqdn":"{{fqdn}}","manager":"%s","callback":"{{instanceCallback}}"}\'';
    private static $inspectCommand = 'mco playbook run helio::cluster::node::inspect --input \'{"node_fqdn":"{{fqdn}}","callback":"{{instanceCallback}}"}\'';
    private static $getRunnerIdCommand = 'mco playbook run helio::cluster::node::getid --input \'{"node_fqdn":"{{fqdn}}","callback":"{{instanceCallback}}"}\'';
    private static $dispatchCommand = 'mco playbook run helio::task::update --input \'{"cluster_address":"%s","task_ids":"[%s]"}\'';
    private static $joinWorkersCommand = 'mco playbook run helio::queue --input \'{"cluster_join_token":"%s","cluster_join_address":"%s","cluster_join_count":"%s","manager_id":"%s","callback":"{{jobCallback}}"}\'';
    private static $updateJobCommand = 'mco playbook run helio::job::update --input \'{"node":"%s","ids":"%s","user_id":"%s"}\'';
    private static $serviceScaleCommand = 'mco playbook run helio::cluster::services::scale --input \'{"node":"%s","services":{{serviceScaleArray}}}\'';
    private static $serviceRemoveCommand = 'mco playbook run helio::cluster::services::remove --input \'{"node":"%s","services":{{serviceRemoveArray}}}\'';
    private static $nodeCleanupCommand = 'mco playbook run helio::cluster::node::cleanup --input \'{"node_fqdn":"{{fqdn}}"}\'';

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
    public function getInventory(): ?string
    {
        return ServerUtility::executeShellCommand($this->parseCommand(self::$inventoryCommand));
    }

    /**
     * @return mixed
     */
    public function startComputing(): ?string
    {
        return ServerUtility::executeShellCommand($this->parseCommand(self::$startComputeCommand));
    }

    /**
     * @return mixed
     */
    public function stopComputing(): ?string
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
     * @param  array     $jobIDs
     * @throws Exception
     */
    public function updateJob(array $jobIDs): void
    {
        if (!$this->job) {
            throw new \InvalidArgumentException('job is required');
        }

        $manager = $this->job->getManager();
        if (!$manager) {
            throw new \Exception('This should not happen! Manager is required.');
        }

        // we're good
        if (!$manager->works()) {
            throw new \Exception('This should not happen! Manager must work.');
        }

        ServerUtility::executeShellCommand($this->parseCommand(self::$updateJobCommand, false, [
            $manager->getFqdn(),
            implode(',', $jobIDs),
            $this->job->getOwner() ? $this->job->getOwner()->getId() : null,
        ]));
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
        if ($manager->works() && JobStatus::READY_PAUSED !== $this->job->getStatus()) {
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

        $manager = $this->job->getManager();
        if (!$manager) {
            return false;
        }

        /** @var Job $job */
        foreach ($manager->getJobs() as $job) {
            if ($job === $this->job || $job->getId() === $this->job->getId()) {
                continue;
            }
            // if there are other jobs than the current one, don't remove the manager.
            return false;
        }

        /* @var Manager $manager */
        return ServerUtility::executeShellCommand($this->parseCommand(self::$deleteManagerCommand, false, [$manager->getName(), $manager->getFqdn()]));
    }

    /**
     * @return string|null
     */
    public function inspect(): ?string
    {
        return ServerUtility::executeShellCommand($this->parseCommand(self::$inspectCommand, true));
    }

    /**
     * @return string|null
     */
    public function removeInstance(): ?string
    {
        $this->ensureRunnerIdIsSet();

        return ServerUtility::executeShellCommand($this->parseCommand(self::$removeNodeCommand, false, [$this->instance->getRunnerId()]));
    }

    public function dispatchReplicas(array $executionsWithNewReplicaCount): ?string
    {
        $executionReplicasArray = [];
        /** @var Execution $execution */
        foreach ($executionsWithNewReplicaCount as $execution) {
            $replica = $execution->getReplicas() ?? JobFactory::getDispatchConfigOfJob($this->job, $execution)->getDispatchConfig()->getReplicaCountForJob($this->job);
            $executionReplicasArray[] = [
                'service' => $execution->getServiceName(),
                'scale' => $replica,
            ];
        }

        $command = str_replace('{{serviceScaleArray}}', json_encode($executionReplicasArray), self::$serviceScaleCommand);
        $command = $this->parseCommand($command, false, [$this->job->getManager()->getFqdn()]);

        return ServerUtility::executeShellCommand($command);
    }

    public function removeExecution(Execution $execution): string
    {
        $servicesArray = [$execution->getServiceName()];

        $command = str_replace('{{serviceRemoveArray}}', json_encode($servicesArray), self::$serviceRemoveCommand);
        $command = $this->parseCommand($command, false, [$this->job->getManager()->getFqdn()]);

        return ServerUtility::executeShellCommand($command);
    }

    public function nodeCleanup(): ?string
    {
        $command = $this->parseCommand(self::$nodeCleanupCommand, false);

        return ServerUtility::executeShellCommand($command);
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
                '{{fqdn}}',
                '{{instanceCallback}}',
                '{{jobCallback}}',
                '{{jobId}}',
                '{{instanceId}}',
            ],
            [
                '\\"',
                $this->instance->getFqdn(),
                ServerUtility::getBaseUrl() . 'api/instance/callback?instanceid=' . $this->instance->getId(),
                $this->job ? ServerUtility::getBaseUrl() . 'api/job/callback?jobid=' . $this->job->getId() : '',
                $this->job ? $this->job->getId() : '',
                $this->instance->getId(),
            ],
            $command
        );

        return vsprintf('ssh %s@%s "' . $command . '"' . ($waitForResult ? '' : ' > /dev/null 2>&1 &'), $params);
    }
}
