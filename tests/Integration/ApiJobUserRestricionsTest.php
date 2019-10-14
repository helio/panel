<?php

namespace Helio\Test\Integration;

use Exception;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Manager;
use Helio\Panel\Model\Preferences\UserPreferences;
use Helio\Panel\Model\User;
use Helio\Panel\Orchestrator\ManagerStatus;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class ApiJobUserRestricionsTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testItWorksWhenNoLimitIsSet(): void
    {
        $user = $this->createUser();

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];

        $response = $this->runWebApp('POST', '/api/job', true, $header, ['name' => 'node', 'type' => 'docker']);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testItWorksWithProperManagerLimitation(): void
    {
        $user = $this->createUser();
        $this->createManager();

        $limits = $user->getPreferences()->getLimits();
        $limits->setManagerNodes(['manager1.manager.example.com']);
        $user->setPreferences(new UserPreferences(['limits' => $limits->jsonSerialize()]));
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush($user);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];

        $response = $this->runWebApp('POST', '/api/job', true, $header, ['name' => 'node', 'type' => 'docker']);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testItFailsWithNonMatchingManagerLimitation(): void
    {
        $user = $this->createUser();
        $this->createManager();

        $limits = $user->getPreferences()->getLimits();
        $limits->setManagerNodes(['manager2.manager.example.com']);
        $user->setPreferences(new UserPreferences(['limits' => $limits->jsonSerialize()]));
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush($user);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];

        $response = $this->runWebApp('POST', '/api/job', true, $header, ['name' => 'node', 'type' => 'docker']);
        $this->assertEquals(StatusCode::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testItWorksWithProperTypeLimitation(): void
    {
        $user = $this->createUser();
        $this->createManager();

        $limits = $user->getPreferences()->getLimits();
        $limits->setJobTypes(['docker']);
        $user->setPreferences(new UserPreferences(['limits' => $limits->jsonSerialize()]));
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush($user);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];

        $response = $this->runWebApp('POST', '/api/job', true, $header, ['name' => 'node', 'type' => 'docker']);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testItFailsWithNonMatchingTypeLimitation(): void
    {
        $user = $this->createUser();
        $this->createManager();

        $limits = $user->getPreferences()->getLimits();
        $limits->setManagerNodes(['busybox']);
        $user->setPreferences(new UserPreferences(['limits' => $limits->jsonSerialize()]));
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush($user);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];

        $response = $this->runWebApp('POST', '/api/job', true, $header, ['name' => 'node', 'type' => 'docker']);
        $this->assertEquals(StatusCode::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
    }

    /**
     * @return User
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
     * @param  User   $user
     * @param  string $name
     * @return Job
     *
     * @throws Exception
     */
    private function createJob(User $user, $name = __CLASS__): Job
    {
        /** @var Job $job */
        $job = (new Job())
            ->setType(JobType::BUSYBOX)
            ->setStatus(JobStatus::READY)
            ->setOwner($user)
            ->setName($name)
            ->setManager($this->createManager())
            ->setCreated();
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush($job);

        return $job;
    }

    /**
     * @return Manager
     * @throws Exception
     */
    private function createManager(): Manager
    {
        $manager = (new Manager())
            ->setStatus(ManagerStatus::READY)
            ->setManagerToken('managertoken')
            ->setWorkerToken('ClusterToken')
            ->setIp('1.2.3.55:46')
            ->setIdByChoria('nodeId')
            ->setFqdn('manager1.manager.example.com');

        $this->infrastructure->getEntityManager()->persist($manager);
        $this->infrastructure->getEntityManager()->flush($manager);

        return $manager;
    }

    /**
     * @param  Job       $job
     * @param  string    $name
     * @return Execution
     * @throws Exception
     */
    private function createExecution(Job $job, $name = __CLASS__): Execution
    {
        /** @var Execution $execution */
        $execution = (new Execution())
            ->setStatus(ExecutionStatus::RUNNING)
            ->setJob($job)
            ->setName($name)
            ->setCreated();
        $this->infrastructure->getEntityManager()->persist($execution);
        $this->infrastructure->getEntityManager()->flush($execution);

        return $execution;
    }
}
