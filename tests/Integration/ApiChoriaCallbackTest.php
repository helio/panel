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
use Helio\Test\Infrastructure\Orchestrator\OrchestratorFactory;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class ApiChoriaCallbackTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();

        // N.B. when you remove jobs/users, you need to reset the instances of orchestrator singleton cache
        // otherwise following tests create new jobs with the same ID, instance cache thinks it knows them still,
        // but the object is actually gone already.
        // Singletons are evil.
        OrchestratorFactory::resetInstances();
    }

    /**
     * @throws Exception
     */
    public function testCallbackWorkerNodeDoesNotCallAssignWhenNoLabelsMatch()
    {
        $user = $this->createAdmin();
        $job = $this->createJob($user);
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', '/api/admin/workerwakeup', true, $tokenHeader, [
            'labels' => [
                'dummy',
            ],
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals('', ServerUtility::getLastExecutedShellCommand(), 'Must not call anything if labels don\'t match');
    }

    /**
     * @throws Exception
     */
    public function testCallbackWorkerNodeDoesNotCallAssignWhenNoActiveExecutions()
    {
        $user = $this->createAdmin();
        $job = $this->createJob($user);
        $job->addLabel('testCallbackWorkerNodeDoesNotCallAssignWhenNoActiveExecutions');

        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush($job);

        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', '/api/admin/workerwakeup', true, $tokenHeader, [
            'labels' => [
                'testCallbackWorkerNodeDoesNotCallAssignWhenNoActiveExecutions',
            ],
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertEquals('', ServerUtility::getLastExecutedShellCommand(), 'Must not call anything if no executions pending');
    }

    /**
     * @throws Exception
     */
    public function testCallbackWorkerNodeDoesCallAssign()
    {
        $user = $this->createAdmin();
        $job = $this->createJob($user, null, 'testCallbackWorkerNodeDoesCallAssign', ['testCallbackWorkerNodeDoesCallAssign']);
        $this->createExecution($job, 'testCallbackWorkerNodeDoesCallAssign', ExecutionStatus::READY);

        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', '/api/admin/workerwakeup', true, $tokenHeader, [
            'labels' => [
                'testCallbackWorkerNodeDoesCallAssign',
            ],
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());
    }

    /**
     * @throws Exception
     */
    public function testCallbackDeleteNodeSetsJobToDeletedStatus()
    {
        $user = $this->createAdmin();
        $manager = $this->createManager('testCallbackDeleteNodeSetsJobToDeletedStatus');
        $job = $this->createJob($user, $manager, 'testCallbackDeleteNodeSetsJobToDeletedStatus');
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('DELETE', '/api/job', true, $tokenHeader, ['id' => $job->getId()]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        /** @var Job $jobFromDatabase */
        $jobFromDatabase = $this->infrastructure->getRepository(Job::class)->find($job->getId());
        $this->assertEquals(JobStatus::DELETING, $jobFromDatabase->getStatus());

        /** @var Manager $managerFromDB */
        $managerFromDB = $this->infrastructure->getRepository(Manager::class)->find($manager->getId());
        $this->assertEquals(ManagerStatus::READY, $managerFromDB->getStatus());

        $response = $this->runWebApp('POST', sprintf('/api/job/callback?id=%s', $job->getId()), true, $tokenHeader, [
            'nodes' => $manager->getName(),
            'deleted' => 1,
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        /** @var Job $jobFromDatabase */
        $jobFromDatabase = $this->infrastructure->getRepository(Job::class)->find($job->getId());
        $this->assertEquals(JobStatus::DELETED, $jobFromDatabase->getStatus());

        /** @var Manager $managerFromDB */
        $managerFromDB = $this->infrastructure->getRepository(Manager::class)->find($manager->getId());
        $this->assertEquals(ManagerStatus::REMOVED, $managerFromDB->getStatus());
    }

    /**
     * @return User
     * @throws Exception
     */
    private function createAdmin(): User
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

    private function createJob(User $user, Manager $manager = null, $name = __CLASS__, array $labels = []): Job
    {
        $manager = $manager ?? $this->createManager($name);

        $job = (new Job())
            ->setType(JobType::BUSYBOX)
            ->setStatus(JobStatus::READY)
            ->setOwner($user)
            ->setName($name)
            ->setManager($manager)
            ->setLabels($labels)
            ->setCreated();

        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush($job);

        return $job;
    }

    private function createManager($name = __CLASS__): Manager
    {
        $manager = (new Manager())
            ->setName($name)
            ->setStatus(ManagerStatus::READY)
            ->setFqdn($name . '.example')
            ->setManagerToken('sometoken')
            ->setWorkerToken('someworkertoken')
            ->setIp('127.0.0.1')
            ->setCreated();
        $this->infrastructure->getEntityManager()->persist($manager);
        $this->infrastructure->getEntityManager()->flush($manager);

        return $manager;
    }

    /**
     * @param  Job       $job
     * @param  string    $name
     * @param  int       $status
     * @return Execution
     * @throws Exception
     */
    private function createExecution(Job $job, $name = __CLASS__, int $status = ExecutionStatus::RUNNING): Execution
    {
        /** @var Execution $execution */
        $execution = (new Execution())
            ->setStatus($status)
            ->setJob($job)
            ->setName($name)
            ->setCreated();
        $this->infrastructure->getEntityManager()->persist($execution);
        $this->infrastructure->getEntityManager()->flush($execution);

        return $execution;
    }
}
