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
use Helio\Test\Infrastructure\Orchestrator\OrchestratorFactory;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class ApiJobTest extends TestCase
{
    /**
     * @var Job
     */
    protected $job;
    /**
     * @var User
     */
    protected $user;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->user = (new User())
            ->setAdmin(1)
            ->setName('testuser')
            ->setEmail('test-autoscaler@example.com'
            )->setActive(true)->setCreated();
        $this->infrastructure->getEntityManager()->persist($this->user);

        $this->job = (new Job())
            ->setType(JobType::BUSYBOX)
            ->setStatus(JobStatus::READY)
            ->setOwner($this->user)->setName('ApiJobTest')
            ->setManagerToken('managertoken')
            ->setInitManagerIp('1.2.3.55')
            ->setManagerNodes(['manager1.manager.example.com'])
            ->setCreated();
        $this->infrastructure->getEntityManager()->persist($this->job);

        $this->infrastructure->getEntityManager()->flush();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->infrastructure->getEntityManager()->remove($this->user);
        $this->infrastructure->getEntityManager()->remove($this->job);
        $this->infrastructure->getEntityManager()->flush();

        // N.B. when you remove jobs/users, you need to reset the instances of orchestrator singleton cache
        // otherwise following tests create new jobs with the same ID, instance cache thinks it knows them still,
        // but the object is actually gone already.
        // Singletons are evil.
        OrchestratorFactory::resetInstances();

        $this->user = null;
        $this->job = null;
    }

    /**
     * @throws Exception
     */
    public function testThatIdParameterBehavesLikeJobIdOnJobApi()
    {
        $statusResult = $this->runWebApp('GET', '/api/job/isready?jobid=' . $this->job->getId(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $this->job)['token']]);
        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode());

        $statusResult = $this->runWebApp('GET', '/api/job/isready?id=' . $this->job->getId(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $this->job)['token']]);
        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode(), 'on the job api, the parameter "id" should be synonym to "jobid".');
    }

    /**
     * @throws Exception
     */
    public function testJobIsDoneReportsCorrectly(): void
    {
        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $this->job)['token']];
        $executionId = base64_encode(random_bytes(8));
        $statusResult = $this->runWebApp('GET', '/api/job/isdone?id=' . $this->job->getId(), true, $header);
        $this->assertEquals(StatusCode::HTTP_FAILED_DEPENDENCY, $statusResult->getStatusCode());

        $this->runWebApp('POST', '/api/job/' . $this->job->getId() . '/execute', true, $header, ['name' => $executionId]);

        $statusResult = $this->runWebApp('GET', '/api/job/isdone?id=' . $this->job->getId(), true, $header);
        $this->assertEquals(StatusCode::HTTP_FAILED_DEPENDENCY, $statusResult->getStatusCode());

        /** @var Execution $exec */
        $exec = $this->infrastructure->getRepository(Execution::class)->findOneBy(['name' => $executionId]);
        $exec->setStatus(ExecutionStatus::DONE);
        $this->infrastructure->getEntityManager()->persist($exec);
        $this->infrastructure->getEntityManager()->flush();

        $statusResult = $this->runWebApp('GET', '/api/job/isdone?id=' . $this->job->getId(), true, $header, ['billingReference' => $executionId]);
        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testJobCreationAndUpdateWorks(): void
    {
        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']];
        $updateResponse = $this->runWebApp('POST', '/api/job', true, $header, ['name' => 'testJobCreationAndUpdateWorks', 'type' => 'docker', 'id' => $this->job->getId()]);
        $this->assertEquals(StatusCode::HTTP_OK, $updateResponse->getStatusCode());
        $body = json_decode((string) $updateResponse->getBody(), true);
        $this->assertArrayHasKey('id', $body);
        $this->assertEquals($this->job->getId(), $body['id']);

        $response = $this->runWebApp('GET', '/api/job?id=' . $this->job->getId(), true, $header);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals($this->job->getId(), $body['id']);
        $this->assertEquals(JobStatus::READY, $body['status']);
    }
}
