<?php

namespace Helio\Panel\Job\Docker;

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
            ->setFixedReplicaCount(1)// enforce call of dispatch command on every new execution
            ->setImage($this->job->getConfig('image'))
            ->setEnvVariables(
                array_merge($this->job->getConfig('env', []), [
                    'HELIO_JOBID' => $this->job->getId(),
                    'HELIO_TOKEN' => JwtUtility::generateToken(null, null, null, $this->job)['token'],
                    'REPORT_URL' => ServerUtility::getBaseUrl() . ExecUtility::getExecUrl($this->job, 'submitresult', $this->execution),
                ])
            )
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
