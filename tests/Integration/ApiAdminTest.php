<?php

namespace Helio\Test\Integration;

use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\User;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class ApiAdminTest extends TestCase
{
    /** @var Job $job */
    protected $job;
    /** @var User $user */
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        // initiate testSet
        /** @var User $user */
        $user = (new User())->setAdmin(1)->setActive(1)->setEmail('admin@example.com')->setName('testuser')->setCreated();
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush();

        /** @var Job $job */
        $job = (new Job())->setType(JobType::VF_DOCKER)->setOwner($user)->setCreated()->setStatus(JobStatus::READY)->setConfig([]);
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush();

        $execution1 = (new Execution())->setJob($job)->setCreated()->setStatus(ExecutionStatus::RUNNING);
        $execution2 = (new Execution())->setJob($job)->setCreated()->setStatus(ExecutionStatus::UNKNOWN);
        $this->infrastructure->getEntityManager()->persist($execution1);
        $this->infrastructure->getEntityManager()->persist($execution2);
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush();

        $this->user = $user;
        $this->job = $job;
    }

    /**
     * @throws \Exception
     */
    public function testStatsEndpoint(): void
    {
        $this->markTestSkipped('TIMESTAMPDIFF() currently doesn\'t work in SQLite');

        $result = $this->runApp('GET', '/api/admin/stat', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);

        $this->assertEquals(StatusCode::HTTP_OK, $result->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testGetJobHiera(): void
    {
        $result = $this->runApp('GET', '/api/admin/getJobHiera?jobid=' . $this->job->getId(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);
        $hiera = (string)$result->getBody();
$debug="".$result->getBody();
        $this->assertEquals(StatusCode::HTTP_OK, $result->getStatusCode());
        $this->assertStringContainsString('  service_name: vfdocker-', $hiera);
    }
}