<?php

namespace Helio\Panel\Job;

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
     */
    public function __construct(Job $job, Execution $execution = null)
    {
        $this->job = $job;
        $this->execution = $execution;
    }

    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return bool
     *
     * @throws Exception
     */
    public function stop(array $params, RequestInterface $request, ResponseInterface $response = null): bool
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
}