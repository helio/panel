<?php

namespace Helio\Panel\Job;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use \Exception;
use Helio\Panel\App;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Execution\ExecutionStatus;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\StatusCode;


/**
 * Class AbstractExecute
 *
 * @package Helio\Panel\Job\Vfdocker
 */
abstract class AbstractExecute implements JobInterface, DispatchableInterface
{

    /**
     * @var Job
     */
    protected $job;
    /**
     * @var Execution
     */
    protected $execution;

    /**
     * Execute constructor.
     *
     * @param Job $job
     * @param Execution|null $execution
     * @throws Exception
     */
    public function __construct(Job $job, Execution $execution = null)
    {
        $this->job = $job;
        $this->execution = $execution ?? (new Execution());
    }


    /**
     * @param array $config
     * @return bool
     * @throws Exception
     */
    public function create(array $config): bool
    {
        $this->job->setConfig($config);
        App::getDbHelper()->persist($this->execution);
        App::getDbHelper()->flush();
        return true;
    }


    /**
     * @param array $config
     * @return bool
     * @throws Exception
     */
    public function run(array $config): bool
    {

        $this->execution->setJob($this->job)->setCreated()->setStatus(ExecutionStatus::READY);
        App::getDbHelper()->persist($this->execution);
        App::getDbHelper()->flush();
        return true;
    }


    /**
     * @param array $config
     *
     * @return bool
     *
     * @throws Exception
     */
    public function stop(array $config): bool
    {
        $executions = $this->execution ? [$this->execution] : DbHelper::getInstance()->getRepository(Execution::class)->findBy(['job' => $this->job]);
        /** @var Execution $execution */
        foreach ($executions as $execution) {
            if (ExecutionStatus::isValidPendingStatus($execution->getStatus() || ExecutionStatus::isRunning($execution->getStatus()))) {
                $execution->setStatus(ExecutionStatus::TERMINATED);
                App::getDbHelper()->persist($execution);
            }
        }
        App::getDbHelper()->flush();

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
                ]), null, JSON_UNESCAPED_SLASHES);
            } catch (OptimisticLockException $e) {
                // trying next execution if the current one was modified in the meantime
            }
        }
        return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
    }
}