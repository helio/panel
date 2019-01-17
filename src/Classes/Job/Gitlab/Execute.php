<?php

namespace Helio\Panel\Job\Gitlab;

use Helio\Panel\Job\DispatchableInterface;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Job\JobInterface;
use Helio\Panel\Model\Job;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Execute implements JobInterface, DispatchableInterface
{
    /**
     * @var Job
     */
    protected $job;

    public function __construct(Job $job)
    {
        $this->job = $job;
    }

    public function stop(array $params): bool
    {
        return true;
    }

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
                'GITLAB_TAGS' => $this->job->getConfig('gitlabTags'),
               // 'GITLAB_TOKEN' =>
            ]);
    }


    /**
     * @param array $params
     * @return bool
     */
    public function create(array $params): bool
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