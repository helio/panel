<?php

namespace Helio\Panel\Model\Preferences;

class UserLimits implements \JsonSerializable
{
    private const DEFAULT_LIMIT_RUNNING_JOBS = 5;
    private const DEFAULT_LIMIT_RUNNING_EXECUTIONS = 10;

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

    public function __construct(array $limits = [])
    {
        $this->runningJobs = $limits['running_jobs'] ?? self::DEFAULT_LIMIT_RUNNING_JOBS;
        $this->runningExecutions = $limits['running_executions'] ?? self::DEFAULT_LIMIT_RUNNING_EXECUTIONS;
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

    public function jsonSerialize(): array
    {
        return [
            'running_jobs' => $this->runningJobs,
            'running_executions' => $this->runningExecutions,
        ];
    }
}
