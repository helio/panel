<?php

namespace Helio\Panel\Job\Ep85;

use \Exception;
use \DateTime;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use Helio\Panel\App;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Model\Execution;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Execute extends AbstractExecute
{


    /**
     * @param array $config
     * @return bool
     * @throws Exception
     */
    public function run(array $config): bool
    {
        $this->execution = $this->execution->setJob($this->job)->setCreated()->setConfig('');
        App::getDbHelper()->persist($this->execution);
        App::getDbHelper()->flush();

        $idf = array_key_exists('idf', $config) ? $config['idf'] : ExecUtility::getExecUrl($this->job, 'work/getidfdata', $this->execution);
        $epw = array_key_exists('epw', $config) ? $config['epw'] : ExecUtility::getExecUrl($this->job, 'work/getwetherdata', $this->execution);

        $outConfig = [
            'idf' => $idf,
            'idf_sha1' => ServerUtility::getHashOfString($idf),
            'epw' => $epw,
            'epw_sha1' => ServerUtility::getHashOfString($epw),
            'report' => array_key_exists('report_url', $config) ? $config['report_url'] : ExecUtility::getExecUrl($this->job, 'submitresult'),
            'upload' => array_key_exists('upload_url', $config) ? $config['upload_url'] : ExecUtility::getExecUrl($this->job, 'upload'),
        ];

        if (array_key_exists('run_id', $config)) {
            $this->execution->setName($config['run_id']);
        }

        $this->execution->setStatus(ExecutionStatus::READY)
            ->setConfig(json_encode($outConfig, JSON_UNESCAPED_SLASHES));

        App::getApp()->getDbHelper()->persist($this->execution);
        App::getApp()->getDbHelper()->persist($this->job);
        App::getApp()->getDbHelper()->flush();

        return true;
    }


    /**
     * @return DispatchConfig
     * @throws Exception
     */
    public function getDispatchConfig(): DispatchConfig
    {
        return (new DispatchConfig())->setImage('gitlab.idling.host:4567/helio/runner/ep85:latest')->setEnvVariables([
            'HELIO_JOBID' => $this->job->getId(),
            'HELIO_TOKEN' => JwtUtility::generateToken(null, $this->job->getOwner(), null, $this->job)['token'],
            'HELIO_URL' => ServerUtility::getBaseUrl()
        ]);
    }


    /**
     * @return int
     */
    protected function calculateRuntime(): int {
        return $this->execution->getConfig('estimated_runtime', 300) + 60;
    }


    /**
     * @param array $params
     * @param Response $response
     * @return ResponseInterface
     */
    public function getwetherdata(array $params, Response $response): ResponseInterface
    {
        return ExecUtility::downloadFile(__DIR__ . '/example.epw', $response);
    }


    /**
     * @param array $params
     * @param Response $response
     * @return ResponseInterface
     */
    public function getidfdata(array $params, Response $response): ResponseInterface
    {
        return ExecUtility::downloadFile(__DIR__ . '/example.idf', $response);
    }
}