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
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return bool
     */
    public function create(array $params, RequestInterface $request, ResponseInterface $response = null): bool
    {
        return true;
    }


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return bool
     * @throws Exception
     */
    public function run(array $params, RequestInterface $request, ResponseInterface $response): bool
    {
        $this->execution = $this->execution ?? (new Execution())->setJob($this->job)->setCreated()->setConfig('');
        App::getDbHelper()->persist($this->execution);
        App::getDbHelper()->flush();

        $inConfig = json_decode(trim((string)$request->getBody()), true) ?? [];
        $idf = array_key_exists('idf', $inConfig) ? $inConfig['idf'] : array_key_exists('idf', $params) ? $params['idf'] : ExecUtility::getExecUrl($this->job, 'work/getidfdata', $this->execution);
        $epw = array_key_exists('epw', $inConfig) ? $inConfig['epw'] : array_key_exists('epw', $params) ? $params['epw'] : ExecUtility::getExecUrl($this->job, 'work/getwetherdata', $this->execution);

        $outConfig = [
            'idf' => $idf,
            'idf_sha1' => ServerUtility::getHashOfString($idf),
            'epw' => $epw,
            'epw_sha1' => ServerUtility::getHashOfString($epw),
            'report_url' => $params['report_url'] ?? ''
        ];

        if (array_key_exists('run_id', $params)) {
            $this->execution->setName($params['run_id']);
        }

        $this->execution->setStatus(ExecutionStatus::READY)
            ->setConfig(json_encode($outConfig, JSON_UNESCAPED_SLASHES));

        App::getApp()->getDbHelper()->persist($this->execution);
        App::getApp()->getDbHelper()->persist($this->job);
        App::getApp()->getDbHelper()->flush();

        return true;
    }


    /**
     * @param array $params
     * @param Response $response
     * @return ResponseInterface
     * @throws Exception
     */
    public function getnextinqueue(array $params, Response $response): ResponseInterface
    {
        $executions = App::getDbHelper()->getRepository(Execution::class)->findBy(['job' => $this->job, 'status' => ExecutionStatus::READY], ['priority' => 'ASC', 'created' => 'ASC'], 5);
        /** @var Execution $execution */
        foreach ($executions as $execution) {
            try {
                /** @var Execution $lockedExecution */
                $lockedExecution = App::getDbHelper()->getRepository(Execution::class)->find($execution->getId(), LockMode::OPTIMISTIC, $execution->getVersion());
                $lockedExecution->setStatus(ExecutionStatus::RUNNING);
                App::getDbHelper()->flush();
                return $response->withJson(array_merge(json_decode($lockedExecution->getConfig(), true), [
                    'id' => (string)$lockedExecution->getId(),
                    'report' => ExecUtility::getExecUrl($this->job, 'work/submitresult'),
                    'upload' => ExecUtility::getExecUrl($this->job, 'upload'),
                ]), null, JSON_UNESCAPED_SLASHES);
            } catch (OptimisticLockException $e) {
                // trying next execution if the current one was modified in the meantime
            }
        }
        return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
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


    /**
     * @param array $params
     * @param Response $response
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function submitresult(array $params, Response $response, RequestInterface $request): ResponseInterface
    {
        if ($this->execution && array_key_exists('success', $params) && $params['success']) {
            /** @var Execution $execution */
            $this->execution->setStatus(ExecutionStatus::DONE);
            $this->execution->setStats((string)$request->getBody());
            DbHelper::getInstance()->persist($this->execution);
            DbHelper::getInstance()->flush();
            return $response;
        }
        return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
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
}