<?php

namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;

class Gitlab implements JobInterface
{
    /**
     * @var Job
     */
    protected $job;

    public function __construct(Job $job)
    {
        $this->job = $job;
    }

    public function run(array $params): bool
    {
        return true;
    }

    public function stop(array $params): bool
    {
        return true;
    }
}