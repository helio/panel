<?php

namespace Helio\Panel\Model\Preferences;

class UserLimits implements \JsonSerializable
{
    private const DEFAULT_LIMIT_RUNNING_JOBS = 5;
    private const DEFAULT_LIMIT_RUNNING_EXECUTIONS = 10;
    private const DEFAULT_LIMIT_JOB_TYPES = [];
    private const DEFAULT_LIMIT_MANAGER_NDOES = [];

    /**
     * Max allowed running jobs.
     * @var int
     */
    protected $runningJobs;

    /**
     * Max allowed running executions per job.
     * @var int
     */
    protected $runningExecutions;

    /**
     * JobTypes this user is restricted to access. Empty means: can access all.
     * @var array
     */
    protected $jobTypes;

    /**
     * Managers this user is restricted to use. Empty means: can use all and create new ones.
     * @var array
     */
    protected $managerNodes;

    public function __construct(array $limits = [])
    {
        $this->runningJobs = $limits['running_jobs'] ?? self::DEFAULT_LIMIT_RUNNING_JOBS;
        $this->runningExecutions = $limits['running_executions'] ?? self::DEFAULT_LIMIT_RUNNING_EXECUTIONS;
        $this->jobTypes = $limits['job_types'] ?? self::DEFAULT_LIMIT_JOB_TYPES;
        $this->managerNodes = $limits['manager_nodes'] ?? self::DEFAULT_LIMIT_MANAGER_NDOES;
    }

    public function getRunningJobs(): int
    {
        return $this->runningJobs;
    }

    public function setRunningJobs(int $runningJobs): void
    {
        $this->runningJobs = $runningJobs;
    }

    public function getRunningExecutions(): int
    {
        return $this->runningExecutions;
    }

    public function setRunningExecutions(int $runningExecutions): void
    {
        $this->runningExecutions = $runningExecutions;
    }

    /**
     * @return array
     */
    public function getJobTypes(): array
    {
        return $this->jobTypes;
    }

    /**
     * @param array $jobTypes
     */
    public function setJobTypes(array $jobTypes): void
    {
        $this->jobTypes = $jobTypes;
    }

    /**
     * @return array
     */
    public function getManagerNodes(): array
    {
        return $this->managerNodes;
    }

    /**
     * @param array $managerNodes
     */
    public function setManagerNodes(array $managerNodes): void
    {
        $this->managerNodes = $managerNodes;
    }

    public function jsonSerialize(): array
    {
        return [
            'running_jobs' => $this->runningJobs,
            'running_executions' => $this->runningExecutions,
            'job_types' => $this->jobTypes,
            'manager_nodes' => $this->managerNodes,
        ];
    }
}
