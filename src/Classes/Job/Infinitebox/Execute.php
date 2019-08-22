<?php

namespace Helio\Panel\Job\Infinitebox;

use Exception;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Utility\JwtUtility;

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
            ->setArgs(['/bin/sh', '-c', escapeshellcmd('i=0; while true; do echo "$i: $(date)"; i=$((i+1)); sleep 10; done')])
            ->setEnvVariables([
                'HELIO_JOBID' => $this->job->getId(),
                'HELIO_TOKEN' => JwtUtility::generateToken(null, null, null, $this->job)['token'],
            ]);
    }

    /**
     * @return int
     */
    protected function calculateRuntime(): int
    {
        return 0;
    }
}
