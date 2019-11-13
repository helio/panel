<?php

namespace Helio\Test\Integration;

use Exception;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Manager;
use Helio\Panel\Model\User;
use Helio\Panel\Orchestrator\ManagerStatus;
use Helio\Panel\Repositories\ExecutionRepository;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class ApiJobSlidingWindowTest extends TestCase
{
    /**
     * @var Job
     */
    private $jobToRun;
    /**
     * @var Job
     */
    private $nextJob;
    /**
     * @var Execution
     */
    private $doneExec;
    /**
     * @var Execution
     */
    private $exec1;
    /**
     * @var Execution
     */
    private $exec2;
    /**
     * @var Execution
     */
    private $exec3;
    /**
     * @var Execution
     */
    private $nextExec;
    /**
     * @var Execution
     */
    private $nextExec2;
    /**
     * @var ExecutionRepository
     */
    private $repository;
    /**
     * @var array
     */
    private $tokenHeader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->infrastructure->getRepository(Execution::class);

        $user = $this->createUser(function (User $user) {
            $user->setOrigin(ServerUtility::get('KOALA_FARM_ORIGIN'));
            $user->setAdmin(true);
        });
        $this->tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];

        $this->jobToRun = $this->createJob(
            $user,
            JobStatus::READY,
            function (Job $job) {
                $job->setLabels(['render']);
            }
        );
        $this->nextJob = $this->createJob(
            $user,
            JobStatus::READY,
            function (Job $job) {
                $job->setLabels(['render']);
            }
        );
        $this->doneExec = $this->createExecution(
            $this->jobToRun,
            ExecutionStatus::DONE,
            function (Execution $execution) {
                $execution->setReplicas(0);
            }
        );
        $this->exec1 = $this->createExecution(
            $this->jobToRun,
            ExecutionStatus::READY,
            function (Execution $execution) {
                $execution->setReplicas(0);
            }
        );
        $this->exec2 = $this->createExecution(
            $this->jobToRun,
            ExecutionStatus::READY,
            function (Execution $execution) {
                $execution->setReplicas(0);
            }
        );
        $this->exec3 = $this->createExecution(
            $this->jobToRun,
            ExecutionStatus::READY,
            function (Execution $execution) {
                $execution->setReplicas(0);
            }
        );
        $this->nextExec = $this->createExecution(
            $this->nextJob,
            ExecutionStatus::READY,
            function (Execution $execution) {
                $execution->setReplicas(0);
            }
        );
        $this->nextExec2 = $this->createExecution(
            $this->nextJob,
            ExecutionStatus::READY,
            function (Execution $execution) {
                $execution->setReplicas(0);
            }
        );
    }

    /**
     * @throws Exception
     */
    public function testSlidingWindow()
    {
        // ----------- Step 1: New worker has been created. We have now 1 worker running but not yet assigned to cluster.
        $response = $this->runWebApp('POST', '/api/admin/workerwakeup', true,
            $this->tokenHeader, [
            'labels' => ['render'],
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals(0, $this->repository->find($this->doneExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec1->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec2->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec3->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec2->getId())->getReplicas());

        // ----------- Step 1.1: Worker assign to cluster
        $response = $this->runWebApp('POST', sprintf('/api/job/callback?id=%s', $this->jobToRun->getId()), true, $this->tokenHeader, [
            'action' => 'joincluster',
            'manager_id' => $this->jobToRun->getManager()->getId(),
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals(0, $this->repository->find($this->doneExec->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->exec1->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec2->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec3->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec2->getId())->getReplicas());

        // ----------- Step 2: New worker has been created. We have now 2 workers running -> 2 executions have replica to 1
        $response = $this->runWebApp('POST', '/api/admin/workerwakeup', true,
            $this->tokenHeader, [
            'labels' => ['render'],
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $response = $this->runWebApp('POST', sprintf('/api/job/callback?id=%s', $this->jobToRun->getId()), true, $this->tokenHeader, [
            'action' => 'joincluster',
            'manager_id' => $this->jobToRun->getManager()->getId(),
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals(0, $this->repository->find($this->doneExec->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->exec1->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->exec2->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec3->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec2->getId())->getReplicas());

        // ----------- Step 3: First execution is done, find next execution to run
        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult?id=%s', $this->jobToRun->getId(), $this->exec1->getId()), true,
            $this->tokenHeader, [
            'success' => true,
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals(0, $this->repository->find($this->doneExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec1->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->exec2->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->exec3->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec2->getId())->getReplicas());

        // ----------- Step 4: Second execution is done, find next execution to run - not found within same job but found within next job.
        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult?id=%s', $this->jobToRun->getId(), $this->exec2->getId()), true,
            $this->tokenHeader, [
                'success' => true,
            ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals(0, $this->repository->find($this->doneExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec1->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec2->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->exec3->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->nextExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec2->getId())->getReplicas());

        // ----------- Step 5: Next worker has been created, with callback from another job - next execution gets triggered regardless of job id
        $response = $this->runWebApp('POST', '/api/admin/workerwakeup', true,
            $this->tokenHeader, [
                'labels' => ['render'],
            ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $response = $this->runWebApp('POST', sprintf('/api/job/callback?id=%s', $this->jobToRun->getId()), true, $this->tokenHeader, [
            'action' => 'joincluster',
            'manager_id' => $this->jobToRun->getManager()->getId(),
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals(0, $this->repository->find($this->doneExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec1->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec2->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->exec3->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->nextExec->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->nextExec2->getId())->getReplicas());

        // ----------- Step 6: Third execution is done, find next execution to run - not found.
        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult?id=%s', $this->jobToRun->getId(), $this->exec3->getId()), true,
            $this->tokenHeader, [
                'success' => true,
            ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals(0, $this->repository->find($this->doneExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec1->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec2->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec3->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->nextExec->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->nextExec2->getId())->getReplicas());

        // ----------- Step 7: Next job's execution is done, find next execution to run - not found.
        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult?id=%s', $this->nextJob->getId(), $this->nextExec->getId()), true,
            $this->tokenHeader, [
                'success' => true,
            ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals(0, $this->repository->find($this->doneExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec1->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec2->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec3->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec->getId())->getReplicas());
        $this->assertEquals(1, $this->repository->find($this->nextExec2->getId())->getReplicas());

        // ----------- Step 8: Next job's execution is done, find next execution to run - not found.
        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult?id=%s', $this->nextJob->getId(), $this->nextExec2->getId()), true,
            $this->tokenHeader, [
                'success' => true,
            ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals(0, $this->repository->find($this->doneExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec1->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec2->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->exec3->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec->getId())->getReplicas());
        $this->assertEquals(0, $this->repository->find($this->nextExec2->getId())->getReplicas());
    }

    private function createUser(?callable $options = null): User
    {
        /** @var User $user */
        $user = (new User())
            ->setAdmin(1)
            ->setName('testuser')
            ->setEmail(
                'test-autoscaler@example.com'
            )->setActive(true)->setCreated();

        if ($options) {
            $options($user);
        }

        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush($user);

        return $user;
    }

    private function createJob(User $user, int $status = JobStatus::READY, ?callable $options = null): Job
    {
        $manager = (new Manager())
            ->setName('testname')
            ->setStatus(ManagerStatus::READY)
            ->setManagerToken('managertoken')
            ->setWorkerToken('ClusterToken')
            ->setIdByChoria('nodeId')
            ->setIp('1.2.3.55')
            ->setFqdn('manager1.manager.example.com');

        $job = (new Job())
            ->setType(JobType::BLENDER)
            ->setOwner($user)
            ->setStatus($status)
            ->setManager($manager)
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
