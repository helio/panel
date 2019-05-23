<?php

namespace Helio\Panel\Job\Vfdocker;

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


    /**
     * Execute constructor.
     * @param Job $job
     */
    public function __construct(Job $job)
    {
        $this->job = $job;
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
                'HELIO_TOKEN' => $this->job->getToken()
            ]);
    }


    /**
     * @param array $params
     * @param RequestInterface $request
     * @return bool
     */
    public function create(array $params, RequestInterface $request): bool
    {
        // TODO: parse body
        $this->job->setConfig((string)$request->getBody());
        return true;
    }
}