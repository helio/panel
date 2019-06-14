<?php

namespace Helio\Panel\Job;

use Helio\Panel\App;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Helio\Panel\Task\TaskStatus;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;


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
        $tasks = $this->task ? [$this->task] : DbHelper::getInstance()->getRepository(Task::class)->findByJob($this->job);
        /** @var Task $task */
        foreach ($tasks as $task) {
            if (TaskStatus::isValidPendingStatus($task->getStatus() || TaskStatus::isRunning($task->getStatus()))) {
                $task->setStatus(TaskStatus::TERMINATED);
                App::getApp()->getContainer()['dbHelper']->persist($task);
            }
        }
        App::getApp()->getContainer()['dbHelper']->flush();

        return true;
    }
}