<?php

namespace Helio\Panel\Job\Gitlab;

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
     * TODO: Implement
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
            ->setImage('gitlab/gitlab-runner')
            ->setEnvVariables([
                'HELIO_JOBID' => $this->job->getId(),
                'HELIO_TOKEN' => $this->job->getToken(),
                'GITLAB_TAGS' => $this->job->getConfig('gitlabTags')
            ]);
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
        $options = [
            'gitlabEndpoint' => FILTER_SANITIZE_URL,
            'gitlabToken' => FILTER_SANITIZE_STRING,
            'gitlabTags' => FILTER_SANITIZE_STRING
        ];

        $config = [];
        foreach ($options as $name => $filter) {
            $key = filter_var($name, FILTER_SANITIZE_STRING);
            if (array_key_exists($key, $params)) {
                $config[$key] = filter_var($params[$key], $filter);
            }
        }
        $this->job->setConfig($config);
        return true;
    }
}