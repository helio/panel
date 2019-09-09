<?php

namespace Helio\Panel\Job\Infinitebox;

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
            ->setImage('hub.helio.dev:4567/helio/runner/infinitebox:latest')
            ->setArgs(['/bin/sh', '-c', 'i=0; while true; do echo "$i: $(date)"; i=$((i+1)); sleep 10; done'])
            ->setEnvVariables($this->getCommonEnvVariables());
    }

    /**
     * @return int
     */
    protected function calculateRuntime(): int
    {
        return 0;
    }
}
