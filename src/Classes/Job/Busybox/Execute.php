<?php

namespace Helio\Panel\Job\Busybox;

use \Exception;
use Helio\Panel\App;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Utility\JwtUtility;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Execute extends AbstractExecute
{


    /**
     * @return DispatchConfig
     * @throws Exception
     */
    public function getDispatchConfig(): DispatchConfig
    {
        return (new DispatchConfig())
            ->setImage('gitlab.idling.host:4567/helio/runner/busybox:latest')
            ->setArgs(['/bin/sh', '-c', '\'i=0; while [ "$i" -le "${LIMIT:-5}" ]; do echo "$i: $(date)"; i=$((i+1)); sleep 10; done\''])
            ->setEnvVariables([
                'HELIO_JOBID' => $this->job->getId(),
                'HELIO_TOKEN' => JwtUtility::generateToken(null, null, null, $this->job)['token'],
                'LIMIT' => $this->execution ? $this->execution->getConfig('limit', 100) : 100
            ]);
    }

    /**
     * @return int
     */
    protected function calculateRuntime(): int {
        return $this->execution->getConfig('limit', 100) * 10 + 30;
    }
}