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
use Helio\Test\Infrastructure\Helper\TestHelper;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class ApiExecutionTest extends TestCase
{
    public function testNewExecutionSendsProperServicecreateCommandToChoria(): void
    {
        $user = $this->createUser();
        $job = $this->createJob($user, JobType::BLENDER);
        $jobId = $this->createExecutionViaApi($job, $user);
        $this->assertNotNull($jobId);

        $rawCommand = ServerUtility::getLastExecutedShellCommand();
        $this->assertStringContainsString('\\"STORAGE_CREDENTIALS\\":\\"{  \\\\\\"type\\\\\\":  \\\\\\"dummy\\\\\\",  \\\\\\"characterEscapeTest\\\\\\": \\\\\\"&try:asdf@blubb%blah\\/ should work, properly +-\\\\\\",  \\\\\\"newlinetest\\\\\\": \\\\\\"-----BEGIN PRIVATE KEY-----\\\\\\\nfoo\\\\\\"}\\"', $rawCommand);

        $command = TestHelper::unescapeChoriaCommand();
        $this->assertStringContainsString('helio::cluster::services::create', $command);

        $input = TestHelper::getInputFromChoriaCommand();

        $this->assertIsArray($input);
        $this->assertArrayHasKey('services', $input);

        $storageCredentials = json_decode($input['services'][0]['env']['STORAGE_CREDENTIALS'], true);
        $this->assertIsArray($storageCredentials);
        $this->assertEquals('&try:asdf@blubb%blah/ should work, properly +-', $storageCredentials['characterEscapeTest']);
    }

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
        $job = $this->createJob($user, JobType::BLENDER, 'testOverflowExecutionScalesToZeroOnJobWithMaxActiveServices', ['render']);

        for ($i = 4; $i > 0; --$i) {
            $this->createExecutionViaApi($job, $user);
        }

        $executionsFromDb = $this->infrastructure->getRepository(Execution::class)->findBy(['job' => $job]);

        $this->assertCount(4, $executionsFromDb);

        $this->assertEquals(1, $executionsFromDb[0]->getReplicas());
        $this->assertEquals(0, $executionsFromDb[1]->getReplicas());
        $this->assertEquals(0, $executionsFromDb[2]->getReplicas());
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
        $job = $this->createJob($user, JobType::BLENDER, 'testSubmitresultAdjustsReplicaCountOnFollowingJobs', ['render']);

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
        $this->assertEquals(0, $executionsFromDb[2]->getReplicas());
        $this->assertEquals(0, $executionsFromDb[3]->getReplicas());
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws Exception
     */
    public function testSubmitresultSendsScaleCommandIfApplicable(): void
    {
        $user = $this->createUser();
        $job = $this->createJob($user, JobType::BLENDER, 'testSubmitresultSendsScaleCommandIfApplicable', ['render']);

        for ($i = 4; $i > 0; --$i) {
            $this->createExecutionViaApi($job, $user);
        }

        $executionsFromDb = $this->infrastructure->getRepository(Execution::class)->findBy(['job' => $job]);
        $this->assertCount(4, $executionsFromDb);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult', $job->getId()), true, $header, ['id' => $executionsFromDb[0]->getId(), 'result' => 42]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), (string) $response->getBody());

        $this->assertStringContainsString('helio::cluster::services::scale', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('helio::cluster::services::remove', ServerUtility::getLastExecutedShellCommand(0));

        $input = TestHelper::getInputFromChoriaCommand(1);

        $this->assertArrayHasKey('services', $input);
        $this->assertArrayHasKey('node', $input);
        $this->assertEquals('manager1.manager.example.com', $input['node']);
        $servicesCalled = $input['services'];
        $this->assertCount(1, $servicesCalled);

        $this->assertEquals(1, $servicesCalled[0]['scale']);
        $this->assertEquals('blender-' . $job->getId() . '-' . $executionsFromDb[1]->getId(), $servicesCalled[0]['service']);
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
     * @param  array                                 $labels
     * @return Job
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws Exception
     */
    private function createJob(User $user, string $type = JobType::BUSYBOX, string $name = __CLASS__, array $labels = []): Job
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
            ->setLabels($labels)
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
