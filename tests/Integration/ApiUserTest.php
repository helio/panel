<?php

namespace Helio\Test\Integration;

use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\TestCase;

class ApiUserTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->infrastructure->getEntityManager()->getFilters()->enable('deleted');
    }

    /**
     * @throws \Exception
     */
    public function testUserProfile(): void
    {
        $user = new User();
        $user->setName('test');
        $user->setEmail('email@example.org');

        $this->infrastructure->import($user);

        $response = $this->runWebApp('GET', '/api/user', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals($user->getName(), $body['name']);
        $this->assertEquals($user->getEmail(), $body['email']);
    }

    /**
     * @throws \Exception
     */
    public function testUserJobListDisplaysJobs(): void
    {
        $user = new User();
        $this->infrastructure->import($user);
        $job = (new Job())->setName('Test Job')->setStatus(JobStatus::READY)->setOwner($user);
        $this->infrastructure->import($job);

        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('GET', '/api/user/joblist', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('items', $body);
        $this->assertCount(1, $body['items']);
        $this->assertEquals(1, $body['total_hits']);
        $this->assertEquals($job->getId(), $body['items'][0]['id']);
    }

    /**
     * @throws \Exception
     */
    public function testUserJobListReturnsTotalHits(): void
    {
        $user = new User();
        $this->infrastructure->import($user);
        $jobs = [
            (new Job())->setName('Test Job')->setStatus(JobStatus::READY)->setOwner($user),
            (new Job())->setName('Test Job')->setStatus(JobStatus::READY)->setOwner($user),
            (new Job())->setName('Test Job')->setStatus(JobStatus::READY)->setOwner($user),
            (new Job())->setName('Test Job')->setStatus(JobStatus::READY)->setOwner($user),
        ];
        $this->infrastructure->import($jobs);

        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('GET', '/api/user/joblist?limit=1', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('items', $body);
        $this->assertCount(1, $body['items']);
        $this->assertEquals(4, $body['total_hits']);
        $this->assertEquals($jobs[0]->getId(), $body['items'][0]['id']);
    }

    /**
     * @throws \Exception
     */
    public function testJobListDoentIncludeTerminatedJobsByDefault(): void
    {
        $user = new User();
        $this->infrastructure->import($user);
        $job = (new Job())->setName('Test Job')->setStatus(JobStatus::DELETED)->setOwner($user);
        $this->infrastructure->import($job);

        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('GET', '/api/user/joblist', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('items', $body);
        $this->assertCount(0, $body['items']);
        $this->assertEquals(0, $body['total_hits']);
    }

    /**
     * @throws \Exception
     */
    public function testJobListDoentIncludeDeletedJobs(): void
    {
        $user = new User();
        $this->infrastructure->import($user);
        $job = (new Job())->setName('Test Job')->setStatus(JobStatus::READY)->setOwner($user)->setHidden(true);
        $this->infrastructure->import($job);

        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('GET', '/api/user/joblist', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('items', $body);
        $this->assertCount(0, $body['items']);
        $this->assertEquals(0, $body['total_hits']);
    }

    /**
     * @throws \Exception
     */
    public function testJobListIncludingDeletedDoentIncludeDeletedJobs(): void
    {
        $user = new User();
        $this->infrastructure->import($user);
        $job = (new Job())->setName('Test Job')->setStatus(JobStatus::READY)->setOwner($user)->setHidden(1);
        $this->infrastructure->import($job);

        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('GET', '/api/user/joblist?deleted=1', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('items', $body);
        $this->assertCount(0, $body['items']);
        $this->assertEquals(0, $body['total_hits']);
    }

    /**
     * @throws \Exception
     */
    public function testJobListDoesIncludeTerminatedJobsIfRequested(): void
    {
        $user = new User();
        $this->infrastructure->import($user);
        $job = (new Job())->setName('Test Job')->setStatus(JobStatus::DELETED)->setOwner($user);
        $this->infrastructure->import($job);

        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('GET', '/api/user/joblist?deleted=1', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('items', $body);
        $this->assertCount(1, $body['items']);
        $this->assertEquals(1, $body['total_hits']);
        $this->assertEquals($job->getId(), $body['items'][0]['id']);
    }

    /**
     * @throws \Exception
     */
    public function testApiDoesNotSendNewTokenViaCookieWhenCalledAsApi(): void
    {
        $user = new User();
        $this->infrastructure->import($user);
        $job = (new Job())->setName('Test Job')->setStatus(JobStatus::DELETED)->setOwner($user);
        $this->infrastructure->import($job);

        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('GET', '/api/user/joblist?deleted=1', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
    }
}
