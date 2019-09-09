<?php

namespace Helio\Test\Integration;

use Exception;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;
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

    private function createUser(): User
    {
        $user = (new User())
            ->setAdmin(1)
            ->setName('testuser')
            ->setEmail('test-autoscaler@example.com'
            )->setActive(true)->setCreated();
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush($user);

        return $user;
    }

    private function createJob(User $user, $name = __CLASS__): Job
    {
        $job = (new Job())
            ->setType(JobType::BUSYBOX)
            ->setStatus(JobStatus::READY)
            ->setOwner($user)
            ->setName($name)
            ->setManagerToken('managertoken')
            ->setClusterToken('ClusterToken')
            ->setInitManagerIp('1.2.3.55')
            ->setManagerNodes(['manager1.manager.example.com'])
            ->setCreated();
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush($job);

        return $job;
    }

    private function createExecution(Job $execution, $name = __CLASS__): Execution
    {
        $execution = (new Execution())
            ->setStatus(ExecutionStatus::READY)
            ->setJob($execution)
            ->setName($name)
            ->setCreated();
        $this->infrastructure->getEntityManager()->persist($execution);
        $this->infrastructure->getEntityManager()->flush($execution);

        return $execution;
    }
}
