<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
use Exception;
use Helio\Panel\App;
use Helio\Panel\Exception\HttpException;
use Helio\Panel\Model\Job;
use Slim\Http\StatusCode;

/**
 * Trait JobController.
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
     * TODO(mw): unfugly me by removing those nice traits.
     * @var Job[] if & jobID param is CSV, this field is used
     */
    protected $jobs = null;

    /**
     * TODO(mw): unfugly me by removing those nice traits.
     * @var bool whether jobID as CSV value is allowed
     */
    protected $jobIdCSVAllowed = false;

    /**
     * @param RouteInfo $route
     *
     * @return bool
     *
     * @throws Exception
     */
    public function setupJob(RouteInfo $route): bool
    {
        $this->setupUser();

        // if we are properly autorized for the job, everything's fine anyways
        if (App::getApp()->getContainer()->has('job')) {
            $this->job = App::getApp()->getContainer()->get('job');

            return true;
        }

        // otherwise, setup job from param
        $this->setupParams($route);

        $jobId = null;
        foreach (['jobid', 'id'] as $param) {
            if (array_key_exists($param, $this->params)) {
                $jobId = $this->params[$param];
                break;
            }
        }
        if (null !== $jobId) {
            $jobRepository = App::getDbHelper()->getRepository(Job::class);

            if (false !== strpos($jobId, ',') && $this->jobIdCSVAllowed) {
                $ids = explode(',', $jobId);
                $this->jobs = $jobRepository->findBy([
                   'id' => array_map(function (string $id) {
                       return filter_var($id, FILTER_SANITIZE_NUMBER_INT);
                   }, $ids),
                ]);
                if (!count($this->jobs)) {
                    throw new HttpException(StatusCode::HTTP_NOT_FOUND, 'No jobs found');
                }

                return true;
            }
            $jobId = filter_var($jobId, FILTER_SANITIZE_NUMBER_INT);
            $this->job = $jobRepository->find($jobId);
            if (!$this->job) {
                throw new HttpException(StatusCode::HTTP_NOT_FOUND, 'Job not found');
            }

            return true;
        }

        // finally, if there is no job at all, simply create one.
        $this->job = (new Job())
            ->setName('___NEW');

        return true;
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    public function validateJobIsSet(): bool
    {
        if ($this->job) {
            if ('___NEW' !== $this->job->getName()) {
                $this->persistJob();
            }

            return true;
        }

        return false;
    }

    /**
     * Persist.
     *
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
