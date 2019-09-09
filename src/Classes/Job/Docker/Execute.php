<?php

namespace Helio\Panel\Job\Docker;

use Exception;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;

class Execute extends AbstractExecute
{
    /**
     * @return DispatchConfig
     *
     * @throws Exception
     */
    public function getDispatchConfig(): DispatchConfig
    {
        return (new DispatchConfig())
            ->setFixedReplicaCount(1)// enforce call of dispatch command on every new execution
            ->setImage($this->job->getConfig('image'))
            ->setEnvVariables($this->getCommonEnvVariables())
            ->setRegistry($this->job->getConfig('registry', []));
    }

    /**
     * @return int
     */
    protected function calculateRuntime(): int
    {
        return $this->execution->getConfig('estimated_runtime', 3600);
    }
}
