<?php

namespace Helio\Panel\Job\Vfdocker;

use Helio\Panel\App;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Job\DispatchableInterface;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Job\JobInterface;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Execute implements JobInterface, DispatchableInterface
{
    /**
     * @var Job
     */
    protected $job;
    /**
     * @var Task
     */
    protected $task;

    /**
     * Execute constructor.
     *
     * @param Job $job
     * @param Task|null $task
     */
    public function __construct(Job $job, Task $task = null)
    {
        $this->job = $job;
        $this->task = $task;
    }

    /**
     * @param array $params
     * @param RequestInterface $request
     * @return bool
     */
    public function stop(array $params, RequestInterface $request): bool
    {
        return true;
    }


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return bool
     * @throws \Exception
     */
    public function run(array $params, RequestInterface $request, ResponseInterface $response): bool
    {
        $this->task = (new Task())->setJob($this->job)->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()))->setConfig((string)$request->getBody());
        App::getApp()->getContainer()['dbHelper']->persist($this->task);
        App::getApp()->getContainer()['dbHelper']->flush();
        return true;
    }


    /**
     * @return DispatchConfig
     */
    public function getDispatchConfig(): DispatchConfig
    {
        return (new DispatchConfig())
            ->setFixedReplicaCount(1)// enforce call of dispatch command on every new task
            ->setImage($this->job->getConfig('container'))
            ->setEnvVariables(
                array_merge($this->job->getConfig('env', []), [
                    'HELIO_JOBID' => $this->job->getId(),
                    'HELIO_TOKEN' => $this->job->getToken(),
                    'REPORT_URL' => ServerUtility::getBaseUrl() . '/exec/work/submitresult?taskid=' . $this->task->getId() . '&token=' . $this->job->getToken()
                ])
            )
            ->setRegistry($this->job->getConfig('registry', []));
    }


    /**
     * @param array $params
     * @param RequestInterface $request
     * @return bool
     */
    public function create(array $params, RequestInterface $request): bool
    {
        $this->job->setConfig((string)$request->getBody());
        return true;
    }


    /**
     * @param array $params
     * @param Response $response
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function submitresult(array $params, Response $response, RequestInterface $request): ResponseInterface
    {
        if ($this->task && \array_key_exists('success', $params) && $params['success']) {
            /** @var Task $task */
            $this->task->setStatus(TaskStatus::DONE);
            $this->task->setStats((string)$request->getBody());
            DbHelper::getInstance()->persist($this->task);
            DbHelper::getInstance()->flush();
            return $response;
        }
        return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
    }
}