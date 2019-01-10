<?php

namespace Helio\Panel\Job\Gitlab;

use Helio\Panel\Job\JobInterface;
use Helio\Panel\Model\Job;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Execute implements JobInterface
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
}