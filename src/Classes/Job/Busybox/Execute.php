<?php

namespace Helio\Panel\Job\Busybox;

use Helio\Panel\App;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Job\DispatchableInterface;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Job\JobInterface;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Helio\Panel\Task\TaskStatus;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
     * @param ResponseInterface|null $response
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function stop(array $params, RequestInterface $request, ResponseInterface $response = null): bool
    {
        $tasks = DbHelper::getInstance()->getRepository(Task::class)->findByJob($this->job);
        /** @var Task $task */
        foreach ($tasks as $task) {
            $task->setStatus(TaskStatus::TERMINATED);
            App::getApp()->getContainer()['dbHelper']->persist($task);
        }
        App::getApp()->getContainer()['dbHelper']->flush();

        return true;
    }


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface $response
     *
     * @return bool
     *
     * TODO: Implement if necessary
     */
    public function run(array $params, RequestInterface $request, ResponseInterface $response): bool
    {
        return true;
    }


    /**
     * @return DispatchConfig
     */
    public function getDispatchConfig(): DispatchConfig
    {
        return (new DispatchConfig())
            ->setImage('gitlab.idling.host:4567/helio/runner/busybox:latest')
            ->setArgs(['/bin/sh', '-c', '\'i=0; while [ "$i" -le "${LIMIT:-5}" ]; do echo "$i: $(date)"; i=$((i+1)); sleep 10; done\''])
            ->setEnvVariables([
                'HELIO_JOBID' => $this->job->getId(),
                'HELIO_TOKEN' => $this->job->getToken(),
                'LIMIT' => 100
            ]);
    }


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return bool
     *
     * TODO: Implement if necessary
     */
    public function create(array $params, RequestInterface $request, ResponseInterface $response = null): bool
    {
        return true;
    }
}