<?php

namespace Helio\Test\Unit\Repository;

use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Repositories\ExecutionRepository;
use Helio\Test\TestCase;

class ExecutionRepositoryTest extends TestCase
{
    /**
     * @var ExecutionRepository
     */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->infrastructure->getRepository(Execution::class);
    }

    public function testFindExecutionsToStart()
    {
        $job = $this->createJob();
        $anotherJob = $this->createJob(JobStatus::READY, function (Job $job) {
            $job->setPriority(0);
        });
        $execA = $this->createExecution($job, ExecutionStatus::READY, function (Execution $execution) {
            $execution->setReplicas(0);
        });
        $this->createExecution($job, ExecutionStatus::RUNNING, function (Execution $execution) {
            $execution->setReplicas(1);
        });
        $this->createExecution($job, ExecutionStatus::READY, function (Execution $execution) {
            $execution->setReplicas(1);
        });
        $execB = $this->createExecution($anotherJob, ExecutionStatus::READY, function (Execution $execution) {
            $execution->setReplicas(0);
        });

        $executions = array_map(
            function (Execution $exec) {
                return $exec->getId();
            },
            $this->repository->findExecutionsToStart($job->getLabels())
        );
        $this->assertEquals([$execB->getId(), $execA->getId()], $executions);
    }

    private function createJob(int $status = JobStatus::READY, ?callable $options = null): Job
    {
        $job = (new Job())
            ->setType(JobType::BUSYBOX)
            ->setStatus($status)
            ->setCreated();

        if ($options) {
            $options($job);
        }

        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush($job);

        return $job;
    }

    private function createExecution(Job $job, int $status = ExecutionStatus::RUNNING, ?callable $options = null): Execution
    {
        /** @var Execution $execution */
        $execution = (new Execution())
            ->setStatus($status)
            ->setJob($job)
            ->setCreated();

        if ($options) {
            $options($execution);
        }

        $this->infrastructure->getEntityManager()->persist($execution);
        $this->infrastructure->getEntityManager()->flush($execution);

        return $execution;
    }
}
