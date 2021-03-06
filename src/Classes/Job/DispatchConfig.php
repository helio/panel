<?php

namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;

class DispatchConfig
{
    protected $image = '';
    protected $envVariables = [];
    protected $args = [];
    protected $registry = [];
    protected $executionPerReplica = 5;
    protected $fixedReplicaCount;

    /**
     * @return string
     */
    public function getImage(): string
    {
        return $this->image;
    }

    /**
     * @param string $image
     *
     * @return DispatchConfig
     */
    public function setImage(string $image): DispatchConfig
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return array
     */
    public function getEnvVariables(): array
    {
        return $this->envVariables;
    }

    /**
     * @param array $envVariables
     *
     * @return DispatchConfig
     */
    public function setEnvVariables(array $envVariables): DispatchConfig
    {
        $this->envVariables = $envVariables;

        return $this;
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @param array $args
     *
     * @return DispatchConfig
     */
    public function setArgs(array $args): DispatchConfig
    {
        $this->args = $args;

        return $this;
    }

    /**
     * @return array
     */
    public function getRegistry(): array
    {
        return $this->registry;
    }

    /**
     * @param array $config
     *
     * @return DispatchConfig
     */
    public function setRegistry(array $config): DispatchConfig
    {
        $this->registry = $config;

        return $this;
    }

    /**
     * @return int
     */
    public function getExecutionPerReplica(): int
    {
        return $this->executionPerReplica;
    }

    /**
     * @param int $executionPerReplica
     *
     * @return DispatchConfig
     */
    public function setExecutionPerReplica(int $executionPerReplica): DispatchConfig
    {
        $this->executionPerReplica = $executionPerReplica;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFixedReplicaCount()
    {
        return $this->fixedReplicaCount;
    }

    /**
     * @param mixed $fixedReplicaCount
     *
     * @return DispatchConfig
     */
    public function setFixedReplicaCount($fixedReplicaCount): DispatchConfig
    {
        $this->fixedReplicaCount = $fixedReplicaCount;

        return $this;
    }

    /**
     * @param Job $job
     *
     * @return int
     *
     * Note:
     * This is super ugly to pass the job here instead of using $this->job, but it's required because the excutions might differ
     * Singletons suck
     */
    public function getReplicaCountForJob(Job $job): int
    {
        if ($this->getFixedReplicaCount()) {
            return $this->getFixedReplicaCount();
        }

        if (0 === $job->getActiveExecutionCount()) {
            return 0;
        }

        return 1 + ceil(($job->getActiveExecutionCount() - ($job->getActiveExecutionCount() % $this->getExecutionPerReplica())) / $this->getExecutionPerReplica());
    }
}
