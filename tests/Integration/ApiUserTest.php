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
        $admin = new User();
        $admin->setName('test');
        $admin->setEmail('email@example.org');
        $admin->setAdmin(true);
        $admin->setActive(true);

        $nonAdminUser = new User();
        $nonAdminUser->setName('another user');
        $nonAdminUser->setEmail('testing@example.org');
        $nonAdminUser->setActive(true);

        $this->infrastructure->import([$admin, $nonAdminUser]);

        // nonAdminUser accesses his own profile
        $response = $this->runWebApp('GET', '/api/user', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $nonAdminUser)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals($nonAdminUser->getName(), $body['name']);
        $this->assertEquals($nonAdminUser->getEmail(), $body['email']);
        $this->assertEquals($nonAdminUser->isActive(), $body['active']);

        // Admin user accesses nonAdminUser
        $response = $this->runWebApp('GET', '/api/user?id=' . $nonAdminUser->getId(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $admin)['token']]);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals($nonAdminUser->getName(), $body['name']);
        $this->assertEquals($nonAdminUser->getEmail(), $body['email']);
        $this->assertEquals($nonAdminUser->isActive(), $body['active']);

        // Admin user accesses a user which does not exist
        $response = $this->runWebApp('GET', '/api/user?id=3425', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $admin)['token']]);
        $this->assertEquals(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertNotContains('name', $body);
        $this->assertNotContains('email', $body);
        $this->assertNotContains('active', $body);

        // nonAdminUser accesses somebody else's profile
        $response = $this->runWebApp('GET', '/api/user?id=' . $admin->getId(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $nonAdminUser)['token']]);
        $this->assertEquals(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertNotContains('name', $body);
        $this->assertNotContains('email', $body);
        $this->assertNotContains('active', $body);
    }

    /**
     * @throws \Exception
     */
    public function testUserDeletedProfile(): void
    {
        $user = new User();
        $user->setName('user');
        $user->setEmail('testing@example.org');
        $user->setActive(true);

        $this->infrastructure->import([$user]);
        $user = $this->infrastructure->getEntityManager()->find(User::class, $user->getId());
        $token = JwtUtility::generateToken(null, $user);

        $response = $this->runWebApp('GET', '/api/user', true, ['Authorization' => 'Bearer ' . $token['token']]);
        $this->assertEquals(200, $response->getStatusCode());

        $this->infrastructure->getEntityManager()->remove($user);
        $this->infrastructure->getEntityManager()->flush();

        // deleted user accesses his own profile
        $response = $this->runWebApp('GET', '/api/user', true, ['Authorization' => 'Bearer ' . $token['token']]);
        $this->assertEquals(401, $response->getStatusCode());
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
