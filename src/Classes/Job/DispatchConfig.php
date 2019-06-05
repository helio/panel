<?php

namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;

class DispatchConfig
{
    protected $image = '';
    protected $envVariables = [];
    protected $args = [];
    protected $taskPerReplica = 5;

    /**
     * @return string
     */
    public function getImage(): string
    {
        return $this->image;
    }

    /**
     * @param string $image
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
     * @return DispatchConfig
     */
    public function setArgs(array $args): DispatchConfig
    {
        $this->args = $args;
        return $this;
    }


    /**
     * @return int
     */
    public function getTaskCountPerReplica(): int
    {
        return $this->taskPerReplica;
    }


    /**
     * @param Job $job
     * @return int
     */
    public function getReplicaCountForJob(Job $job): int
    {
        if ($job->getActiveTaskCount() === 0) {
            return 0;
        }

        return 1 + ceil(($job->getActiveTaskCount() - ($job->getActiveTaskCount() % $this->getTaskCountPerReplica())) / $this->getTaskCountPerReplica());
    }

}