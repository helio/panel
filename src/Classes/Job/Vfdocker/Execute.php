<?php

namespace Helio\Panel\Job\Vfdocker;

use \Exception;
use \InvalidArgumentException;
use Helio\Panel\App;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Model\Execution;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Execute extends AbstractExecute
{


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return bool
     * @throws Exception
     */
    public function run(array $params, RequestInterface $request, ResponseInterface $response): bool
    {
        if (!$this->execution) {
            $this->execution = new Execution();
        }
        $this->execution->setJob($this->job)->setCreated()->setConfig((string)$request->getBody());
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
                    'REPORT_URL' => ServerUtility::getBaseUrl() . ExecUtility::getExecUrl($this->job, 'work/submitresult', $this->execution)
                ])
            )
            ->setRegistry($this->job->getConfig('registry', []));
    }


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return bool
     */
    public function create(array $params, RequestInterface $request, ResponseInterface $response = null): bool
    {
        $body = (string)$request->getBody();

        // validate JSON
        if ($body && $request->getHeaderLine('Content-Type') === 'application/json' && json_decode($body) === null) {
            LogHelper::debug('Invalid Json supplied: ' . print_r($body, true));
            throw new InvalidArgumentException('Invalid JSON supplied', 1560782976);
        }

        // if no object named config is there, we assume that the whole body is the config... this is merely backwards compatibility
        if (!array_key_exists('config', json_decode($body, true))) {
            $this->job->setConfig($body);
        }
        return true;
    }
}