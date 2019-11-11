<?php

namespace Helio\Test\Unit\Repository;

use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Repositories\JobRepository;
use Helio\Test\TestCase;

class JobRepositoryTest extends TestCase
{
    /**
     * @var JobRepository
     */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->infrastructure->getRepository(Job::class);
    }

    public function testGetExecutionCountHavingReplicas()
    {
        $anotherJob = $this->createJob(JobStatus::READY, function (Job $job) {
            $job->setLabels(['render', 'gpu']);
        });
        $this->createExecution($anotherJob, ExecutionStatus::DONE, function (Execution $execution) {
            $execution->setReplicas(0);
        });

        $jobRenderGPU = $this->createJob(JobStatus::READY, function (Job $job) {
            $job->setLabels(['render', 'gpu']);
        });
        $this->createExecution($jobRenderGPU, ExecutionStatus::READY, function (Execution $execution) {
            $execution->setReplicas(1);
        });
        $this->createExecution($jobRenderGPU, ExecutionStatus::RUNNING, function (Execution $execution) {
            $execution->setReplicas(1);
        });
        $this->createExecution($jobRenderGPU, ExecutionStatus::READY, function (Execution $execution) {
            $execution->setReplicas(0);
        });
        $this->createExecution($jobRenderGPU, ExecutionStatus::DONE, function (Execution $execution) {
            $execution->setReplicas(0);
        });

        $this->assertEquals(0, $this->repository->getExecutionCountHavingReplicas(['foo']));
        $this->assertEquals(2, $this->repository->getExecutionCountHavingReplicas(['render']));
        $this->assertEquals(2, $this->repository->getExecutionCountHavingReplicas(['render', 'gpu']));
        $this->assertEquals(2, $this->repository->getExecutionCountHavingReplicas(['gpu']));
    }

    public function testFindNextJobInQueue()
    {
        $skipJob = $this->createJob(JobStatus::READY, function (Job $job) {
            $job->setLabels(['render']);
        });
        $jobRenderGPU = $this->createJob(JobStatus::READY, function (Job $job) {
            $job->setLabels(['render']);
        });

        $this->assertEquals($jobRenderGPU->getId(), $this->repository->findNextJobInQueue(['render'], $skipJob->getId())->getId());
        $this->assertEquals(null, $this->repository->findNextJobInQueue(['gpu'], $skipJob->getId()));
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
