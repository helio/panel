<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;

/**
 * Trait ServerController
 * @package Helio\Panel\Controller\Traits
 * @method User getUser()
 * @method bool hasUser()
 */
trait JobController
{
    use ParametrizedController;

    /**
     * @var Job
     */
    protected $job;


    /**
     * @return bool
     */
    public function setupJob(): bool
    {
        $this->setupParams();
        $jobId = filter_var($this->params['jobid'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        if ($jobId === 0) {
            return false;
        }
        $this->job = $this->dbHelper->getRepository(Job::class)->find($jobId);
        return true;
    }

    /**
     * Persist
     */
    protected function persistJob(): void
    {
        $this->dbHelper->persist($this->job);
        $this->dbHelper->flush($this->job);
    }
}