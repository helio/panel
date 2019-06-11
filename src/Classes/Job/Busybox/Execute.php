<?php

namespace Helio\Panel\Job\Busybox;

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
     * @return bool
     */
    public function create(array $params, RequestInterface $request): bool
    {
        return true;
    }
}