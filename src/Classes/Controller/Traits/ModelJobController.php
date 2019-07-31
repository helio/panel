<?php

namespace Helio\Panel\Controller\Traits;

use \Exception;
use Helio\Panel\App;
use Helio\Panel\Model\Job;

/**
 * Trait JobController
 * @package Helio\Panel\Controller\Traits
 */
trait ModelJobController
{
    use ModelParametrizedController;
    use ModelUserController;

    /**
     * @var Job
     */
    protected $job;


    /**
     * @return bool
     * @throws Exception
     */
    public function setupJob(): bool
    {
        $this->setupUser();

        // if we are properly autorized for the job, everything's fine anyways
        if (App::getApp()->getContainer()->has('job')) {
            $this->job = App::getApp()->getContainer()->get('job');
            return true;
        }

        // otherwise, setup job from param
        $this->setupParams();
        $jobId = filter_var($this->params['jobid'] ?? ($this->idAlias === 'jobid' ? $this->params['id'] : 0), FILTER_SANITIZE_NUMBER_INT);
        if ($jobId > 0) {
            $this->job = App::getDbHelper()->getRepository(Job::class)->find($jobId);
            return true;
        }

        // finally, if there is no job at all, simply create one.
        $this->job = (new Job())
            ->setName('___NEW')
            ->setOwner($this->user)
            ->setCreated();
        return true;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function validateJobIsSet(): bool {
        if ($this->job) {
            if ($this->job->getName() !== '___NEW') {
                $this->persistJob();
            }
            return true;
        }
        return false;
    }

    /**
     * Persist
     * @throws Exception
     */
    protected function persistJob(): void
    {
        if ($this->job) {
            $this->job->setLatestAction();
            App::getDbHelper()->persist($this->job);
            App::getDbHelper()->flush($this->job);
        }
    }
}