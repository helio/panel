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
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class ApiExecutionTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testHeartbeatActionSetsProperFields(): void
    {
        $user = $this->createUser();
        $job = $this->createJob($user);
        $execution = $this->createExecution($job);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/heartbeat', $job->getId()), true, $header, ['id' => $execution->getId()]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), (string) $response->getBody());

        /** @var Execution $executionFromDatabse */
        $executionFromDatabse = $this->infrastructure->getRepository(Execution::class)->find($execution->getId());

        $this->assertEquals(ExecutionStatus::RUNNING, $executionFromDatabse->getStatus());
        $this->assertNotNull($executionFromDatabse->getStarted());
        $this->assertNotNull($executionFromDatabse->getLatestHeartbeat());
        $this->assertEqualsWithDelta(new \DateTime('now', ServerUtility::getTimezoneObject()), $executionFromDatabse->getStarted(), 1.0);
        $this->assertEqualsWithDelta(new \DateTime('now', ServerUtility::getTimezoneObject()), $executionFromDatabse->getLatestHeartbeat(), 1.0);
    }

    /**
     * @throws Exception
     */
    public function testSubmitresultSetsProperAttributes(): void
    {
        $user = $this->createUser();
        $job = $this->createJob($user);
        $execution = $this->createExecution($job);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult', $job->getId()), true, $header, ['id' => $execution->getId(), 'result' => 42]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), (string) $response->getBody());

        /** @var Execution $executionFromDatabse */
        $executionFromDatabse = $this->infrastructure->getRepository(Execution::class)->find($execution->getId());

        $this->assertEquals(ExecutionStatus::DONE, $executionFromDatabse->getStatus());
        $this->assertNotNull($executionFromDatabse->getLatestHeartbeat());
        $this->assertEqualsWithDelta(new \DateTime('now', ServerUtility::getTimezoneObject()), $executionFromDatabse->getLatestHeartbeat(), 1.0);

        $stats = json_decode($executionFromDatabse->getStats(), true);
        $this->assertArrayHasKey('result', $stats);
        $this->assertEquals(42, $stats['result']);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testOverflowExecutionScalesToZeroOnJobWithMaxActiveServices(): void
    {
        $user = $this->createUser();
        $job = $this->createJob($user, JobType::BLENDER);

        for ($i = 4; $i > 0; --$i) {
            $this->createExecutionViaApi($job, $user);
        }

        $executionsFromDb = $this->infrastructure->getRepository(Execution::class)->findBy(['job' => $job]);

        $this->assertCount(4, $executionsFromDb);

        $this->assertEquals(1, $executionsFromDb[0]->getReplicas());
        $this->assertEquals(1, $executionsFromDb[1]->getReplicas());
        $this->assertEquals(1, $executionsFromDb[2]->getReplicas());
        $this->assertEquals(0, $executionsFromDb[3]->getReplicas());
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testReplicasIsNullOnJobWithoutReplicaMagic(): void
    {
        $user = $this->createUser();
        $job = $this->createJob($user);
        $this->createExecutionViaApi($job, $user);

        $executionsFromDb = $this->infrastructure->getRepository(Execution::class)->findBy(['job' => $job]);

        $this->assertCount(1, $executionsFromDb);
        $this->assertEquals(null, $executionsFromDb[0]->getReplicas());
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws Exception
     */
    public function testSubmitresultAdjustsReplicaCountOnFollowingJobs(): void
    {
        $user = $this->createUser();
        $job = $this->createJob($user, JobType::BLENDER);

        for ($i = 4; $i > 0; --$i) {
            $this->createExecutionViaApi($job, $user);
        }

        $executionsFromDb = $this->infrastructure->getRepository(Execution::class)->findBy(['job' => $job]);
        $this->assertCount(4, $executionsFromDb);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult', $job->getId()), true, $header, ['id' => $executionsFromDb[0]->getId(), 'result' => 42]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), (string) $response->getBody());

        $this->assertEquals(0, $executionsFromDb[0]->getReplicas());
        $this->assertEquals(1, $executionsFromDb[1]->getReplicas());
        $this->assertEquals(1, $executionsFromDb[2]->getReplicas());
        $this->assertEquals(1, $executionsFromDb[3]->getReplicas());
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws Exception
     */
    public function testSubmitresultSendsScaleCommandIfApplicable(): void
    {
        $user = $this->createUser();
        $job = $this->createJob($user, JobType::BLENDER);

        for ($i = 4; $i > 0; --$i) {
            $this->createExecutionViaApi($job, $user);
        }

        $executionsFromDb = $this->infrastructure->getRepository(Execution::class)->findBy(['job' => $job]);
        $this->assertCount(4, $executionsFromDb);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult', $job->getId()), true, $header, ['id' => $executionsFromDb[0]->getId(), 'result' => 42]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), (string) $response->getBody());

        $this->assertStringContainsString('helio::cluster::services::scale', ServerUtility::getLastExecutedShellCommand(2));
        $this->assertStringContainsString('helio::task::update', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());

        $command = ServerUtility::getLastExecutedShellCommand(2);
        $matches = [];
        preg_match("/--input '([^']+)'/", $command, $matches);
        $this->assertNotEmpty($matches);
        $servicesCalled = json_decode($matches[1], true);
        $this->assertCount(2, $servicesCalled);

        $this->assertEquals(0, $servicesCalled[0]['scale']);
        $this->assertEquals('blender-' . $job->getId() . '-' . $executionsFromDb[0]->getId(), $servicesCalled[0]['service']);
        $this->assertEquals(1, $servicesCalled[1]['scale']);
        $this->assertEquals('blender-' . $job->getId() . '-' . $executionsFromDb[3]->getId(), $servicesCalled[1]['service']);
    }

    /**
     * @return User
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws Exception
     */
    private function createUser(): User
    {
        /** @var User $user */
        $user = (new User())
            ->setAdmin(1)
            ->setName('testuser')
            ->setEmail('test-autoscaler@example.com'
            )->setActive(true)->setCreated();
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush($user);

        return $user;
    }

    /**
     * @param  User                                  $user
     * @param  string                                $type
     * @param  string                                $name
     * @return Job
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws Exception
     */
    private function createJob(User $user, $type = JobType::BUSYBOX, $name = __CLASS__): Job
    {
        /** @var Job $job */
        $job = (new Job())
            ->setType($type)
            ->setOwner($user)
            ->setManager((new Manager())
                ->setStatus(ManagerStatus::READY)
                ->setManagerToken('managertoken')
                ->setWorkerToken('ClusterToken')
                ->setIp('1.2.3.55')
                ->setFqdn('manager1.manager.example.com')
            )
            ->setStatus(JobStatus::READY)
            ->setCreated()
            ->setName($name);
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush($job);

        return $job;
    }

    private function createExecution(Job $job, $name = __CLASS__): Execution
    {
        $execution = (new Execution())
            ->setStatus(ExecutionStatus::READY)
            ->setJob($job)
            ->setName($name)
            ->setCreated();
        $this->infrastructure->getEntityManager()->persist($execution);
        $this->infrastructure->getEntityManager()->flush($execution);

        return $execution;
    }

    private function createExecutionViaApi(Job $job, User $user): Execution
    {
        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];
        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute', $job->getId()), true, $header);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), (string) $response->getBody());

        return $this->infrastructure->getRepository(Execution::class)->find(json_decode((string) $response->getBody(), true)['id']);
    }
}
