<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;

/**
 * Trait JobController
 * @package Helio\Panel\Controller\Traits
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
     * @throws \Exception
     */
    public function setupJob(): bool
    {
        $this->setupParams();

        // make it possible to add a new job via api
        if ($this->user !== null && \array_key_exists('jobid', $this->params) && filter_var($this->params['jobid'], FILTER_SANITIZE_STRING) === '_NEW') {

            $job = (new Job())
                ->setName('precreated automatically')
                ->setOwner($this->user)
                ->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()));
            $this->dbHelper->persist($job);
            $this->dbHelper->flush($job);

            $job->setToken(JwtUtility::generateJobIdentificationToken($job));

            $this->user->addJob($job);
            $this->job = $job;

            $this->persistUser();
            $this->persistJob();
            return true;
        }

        // otherwise, setup job from param
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
        $this->job->setLatestAction();
        $this->dbHelper->persist($this->job);
        $this->dbHelper->flush($this->job);
    }
}