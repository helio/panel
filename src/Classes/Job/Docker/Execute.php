<?php

namespace Helio\Panel\Job\Docker;

use \Exception;
use Helio\Panel\Execution\ExecutionStatus;
use \InvalidArgumentException;
use Helio\Panel\App;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Execute extends AbstractExecute
{


    /**
     * @param array $config
     * @return bool
     * @throws Exception
     */
    public function run(array $config): bool
    {
        $this->execution->setJob($this->job)->setCreated()->setConfig($config)->setStatus(ExecutionStatus::READY);
        App::getDbHelper()->persist($this->execution);
        App::getDbHelper()->flush();
        return true;
    }


    /**
     * @return DispatchConfig
     * @throws Exception
     */
    public function getDispatchConfig(): DispatchConfig
    {
        return (new DispatchConfig())
            ->setFixedReplicaCount(1)// enforce call of dispatch command on every new execution
            ->setImage($this->job->getConfig('container'))
            ->setEnvVariables(
                array_merge($this->job->getConfig('env', []), [
                    'HELIO_JOBID' => $this->job->getId(),
                    'HELIO_TOKEN' => JwtUtility::generateToken(null, null, null, $this->job)['token'],
                    'REPORT_URL' => ServerUtility::getBaseUrl() . ExecUtility::getExecUrl($this->job, 'submitresult', $this->execution)
                ])
            )
            ->setRegistry($this->job->getConfig('registry', []));
    }


    /**
     * @return int
     */
    protected function calculateRuntime(): int {
        return $this->execution->getConfig('estimated_runtime', 3600);
    }
}