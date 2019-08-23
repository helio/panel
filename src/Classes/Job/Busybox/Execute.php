<?php

namespace Helio\Panel\Job\Busybox;

use Exception;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;

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
            ->setImage('hub.helio.dev:4567/helio/runner/busybox:latest')
            ->setArgs(['/bin/sh', '-c', escapeshellcmd('i=0; while [ "$i" -le "${LIMIT:-5}" ]; do echo "$i: $(date)"; i=$((i+1)); sleep 10; done; wget -q --header "Authorization: Bearer $HELIO_TOKEN" $SUBMIT_URL')])
            ->setEnvVariables([
                'HELIO_JOBID' => $this->job->getId(),
                'HELIO_TOKEN' => JwtUtility::generateToken(null, null, null, $this->job)['token'],
                'LIMIT' => (int) ($this->execution ? $this->execution->getConfig('limit', $this->job->getConfig('env.LIMIT', 100)) : 100),
                'SUBMIT_URL' => ServerUtility::getBaseUrl() . ExecUtility::getExecUrl($this->job, 'submitresult', $this->execution),
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
