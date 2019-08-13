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

        $this->user = (new User())->setAdmin(1)->setName('testuser')->setEmail('test-autoscaler@example.com')->setActive(true)->setCreated();
        $this->job = (new Job())->setType(JobType::BUSYBOX)->setStatus(JobStatus::READY)->setOwner($this->user)->setName('ApiJobTest')->setManagerToken('managertoken')->setInitManagerIp('1.2.3.55')->setManagerNodes(['manager1.manager.example.com'])->setCreated();
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->flush();
    }

    /**
     * @throws Exception
     */
    public function testThatIdParameterBehavesLikeJobIdOnJobApi()
    {
        $statusResult = $this->runApp('GET', '/api/job/isready?jobid=' . $this->job->getId(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $this->job)['token']]);
        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode());

        $statusResult = $this->runApp('GET', '/api/job/isready?id=' . $this->job->getId(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $this->job)['token']]);
        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode(), 'on the job api, the parameter "id" should be synonym to "jobid".');
    }

    /**
     * @throws Exception
     */
    public function testJobIsDoneReportsCorrectly(): void
    {
        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $this->job)['token']];
        $executionId = base64_encode(random_bytes(8));
        $statusResult = $this->runApp('GET', '/api/job/isdone?id=' . $this->job->getId(), true, $header);
        $this->assertEquals(StatusCode::HTTP_FAILED_DEPENDENCY, $statusResult->getStatusCode());

        $this->runApp('POST', '/api/job/' . $this->job->getId() . '/execute', true, $header, ['name' => $executionId]);

        $statusResult = $this->runApp('GET', '/api/job/isdone?id=' . $this->job->getId(), true, $header);
        $this->assertEquals(StatusCode::HTTP_FAILED_DEPENDENCY, $statusResult->getStatusCode());

        /** @var Execution $exec */
        $exec = $this->infrastructure->getRepository(Execution::class)->findOneBy(['name' => $executionId]);
        $exec->setStatus(ExecutionStatus::DONE);
        $this->infrastructure->getEntityManager()->persist($exec);
        $this->infrastructure->getEntityManager()->flush();

        $tasks = $this->infrastructure->getRepository(Execution::class)->findAll();
        $statusResult = $this->runApp('GET', '/api/job/isdone?id=' . $this->job->getId(), true, $header, ['billingReference' => $executionId]);
        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode());
    }
}
