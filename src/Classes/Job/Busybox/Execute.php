<?php

namespace Helio\Panel\Job\Busybox;

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
        $envVariables = $this->getCommonEnvVariables();
        if (!array_key_exists('LIMIT', $envVariables)) {
            $envVariables['LIMIT'] = (int) ($this->execution ? $this->execution->getConfig('limit', 100) : 100);
        }

        return (new DispatchConfig())
            ->setImage('hub.helio.dev:4567/helio/runner/busybox:latest')
            ->setEnvVariables($envVariables);
    }

    /**
     * @return int
     */
    protected function calculateRuntime(): int
    {
        return ($this->execution ? $this->execution->getConfig('limit', $this->job->getConfig('env.LIMIT', 100)) : 100) * 10 + 30;
    }
}
