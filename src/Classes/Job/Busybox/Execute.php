<?php

namespace Helio\Panel\Job\Busybox;

use Exception;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Utility\ExecUtility;
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
            ->setImage('gitlab.idling.host:4567/helio/runner/busybox:latest')
            ->setArgs(['/bin/sh', '-c', '\'i=0; while [ "$i" -le "${LIMIT:-5}" ]; do echo "$i: $(date)"; i=$((i+1)); sleep 10; done; curl -fsSL -o /dev/null -X PUT -H "Authorization: Bearer $HELIO_TOKEN" $SUBMIT_URL\''])
            ->setEnvVariables([
                'HELIO_JOBID' => $this->job->getId(),
                'HELIO_TOKEN' => JwtUtility::generateToken(null, null, null, $this->job)['token'],
                'LIMIT' => $this->execution ? $this->execution->getConfig('limit', 100) : 100,
                'SUBMIT_URL' => ExecUtility::getExecUrl($this->job, 'submitresult'),
            ]);
    }

    /**
     * @return int
     */
    protected function calculateRuntime(): int
    {
        return $this->execution->getConfig('limit', 100) * 10 + 30;
    }
}
