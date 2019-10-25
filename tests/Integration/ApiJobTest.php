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
use Helio\Test\Infrastructure\Orchestrator\OrchestratorFactory;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class ApiJobTest extends TestCase
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
    public function testThatIdParameterBehavesLikeJobIdOnJobApi()
    {
        $job = $this->createJob($this->createUser(), 'ApiJobTest');

        $statusResult = $this->runWebApp('GET', '/api/job/isready?jobid=' . $job->getId(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $job)['token']]);
        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode());

        $statusResult = $this->runWebApp('GET', '/api/job/isready?id=' . $job->getId(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $job)['token']]);
        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode(), 'on the job api, the parameter "id" should be synonym to "jobid".');
    }

    /**
     * @throws Exception
     */
    public function testJobIsDoneReportsCorrectly(): void
    {
        $job = $this->createJob($this->createUser(), 'ApiJobTest');

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $job)['token']];
        $executionId = base64_encode(random_bytes(8));
        $statusResult = $this->runWebApp('GET', '/api/job/isdone?id=' . $job->getId(), true, $header);
        $this->assertEquals(StatusCode::HTTP_FAILED_DEPENDENCY, $statusResult->getStatusCode());

        $this->runWebApp('POST', '/api/job/' . $job->getId() . '/execute', true, $header, ['name' => $executionId]);

        $statusResult = $this->runWebApp('GET', '/api/job/isdone?id=' . $job->getId(), true, $header);
        $this->assertEquals(StatusCode::HTTP_FAILED_DEPENDENCY, $statusResult->getStatusCode());

        /** @var Execution $exec */
        $exec = $this->infrastructure->getRepository(Execution::class)->findOneBy(['name' => $executionId]);
        $exec->setStatus(ExecutionStatus::DONE);
        $this->infrastructure->getEntityManager()->persist($exec);
        $this->infrastructure->getEntityManager()->flush();

        $statusResult = $this->runWebApp('GET', '/api/job/isdone?id=' . $job->getId(), true, $header, ['billingReference' => $executionId]);
        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testJobCreationAndUpdateWorks(): void
    {
        $user = $this->createUser();
        $job = $this->createJob($user, 'ApiJobTest');

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];
        $updateResponse = $this->runWebApp('POST', '/api/job', true, $header, ['name' => 'testJobCreationAndUpdateWorks', 'type' => 'docker', 'id' => $job->getId()]);
        $this->assertEquals(StatusCode::HTTP_OK, $updateResponse->getStatusCode());
        $body = json_decode((string) $updateResponse->getBody(), true);
        $this->assertArrayHasKey('id', $body);
        $this->assertEquals($job->getId(), $body['id']);

        $response = $this->runWebApp('GET', '/api/job?id=' . $job->getId(), true, $header);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals($job->getId(), $body['id']);
        $this->assertEquals(JobStatus::READY, $body['status']);
    }

    /**
     * @throws Exception
     */
    public function testRunningJobLimit(): void
    {
        $user = $this->createUser();

        $limits = $user->getPreferences()->getLimits();
        $runningJobsLimit = 1;
        $limits->setRunningJobs($runningJobsLimit);
        $user->setPreferences(new UserPreferences(['limits' => $limits->jsonSerialize()]));
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush($user);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];
        for ($i = 0; $i < $limits->getRunningJobs(); ++$i) {
            $this->createJob($user, sprintf('%s-%s', __METHOD__, $i));
        }
        $name = sprintf('%s-exceeded', __METHOD__);
        $response = $this->runWebApp('POST', '/api/job', true, $header, ['name' => $name, 'type' => 'docker']);
        $this->assertEquals(StatusCode::HTTP_FORBIDDEN, $response->getStatusCode(), $name);

        $body = json_decode((string) $response->getBody(), true);
        // notification is not interesting for the below equals check
        unset($body['notification']);
        $body['limits'] = array_filter($body['limits'], function ($key) {
            return in_array($key, ['running_jobs', 'running_executions']);
        }, ARRAY_FILTER_USE_KEY);

        $this->assertEquals([
            'success' => false,
            'message' => sprintf('Limit of running jobs reached. Amount running: %s / Limit: %s. Please contact helio support if you have any questions.', $runningJobsLimit, $runningJobsLimit),
            'limits' => [
                'running_jobs' => 1,
                'running_executions' => 10,
            ],
        ], $body);
    }

    /**
     * @throws Exception
     */
    public function testRunningJobExecutionLimit(): void
    {
        $user = $this->createUser();
        $job = $this->createJob($user);

        $limits = $user->getPreferences()->getLimits();
        $runningExecutionsLimit = 1;
        $limits->setRunningExecutions($runningExecutionsLimit);
        $user->setPreferences(new UserPreferences(['limits' => $limits->jsonSerialize()]));
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush($user);

        $header = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];
        for ($i = 0; $i < $limits->getRunningExecutions(); ++$i) {
            $this->createExecution($job, sprintf('%s-%s', __METHOD__, $i), ExecutionStatus::RUNNING);
        }
        $name = sprintf('%s-exceeded', __METHOD__);
        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute', $job->getId()), true, $header, ['name' => $name]);
        $this->assertEquals(StatusCode::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getBody());

        $body = json_decode((string) $response->getBody(), true);
        // notification is not interesting for the below equals check
        unset($body['notification']);
        $body['limits'] = array_filter($body['limits'], function ($key) {
            return in_array($key, ['running_jobs', 'running_executions']);
        }, ARRAY_FILTER_USE_KEY);

        $this->assertEquals([
            'success' => false,
            'message' => sprintf('Limit of running executions reached. Amount running: %s / Limit: %s. Please contact helio support if you have any questions.', $runningExecutionsLimit, $runningExecutionsLimit),
            'limits' => [
                'running_jobs' => 5,
                'running_executions' => 1,
            ],
        ], $body);
    }

    /**
     * @throws Exception
     */
    public function testDeleteRequestSetsJobToDeletingStatus()
    {
        $user = $this->createUser();
        $job = $this->createJob($user, 'testDeleteRequestSetsJobToDeletingStatus');
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('DELETE', '/api/job', true, $tokenHeader, ['id' => $job->getId()]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
        /** @var Job $jobFromDatabase */
        $jobFromDatabase = $this->infrastructure->getRepository(Job::class)->find($job->getId());
        $this->assertEquals(JobStatus::DELETING, $jobFromDatabase->getStatus());
    }

    public function testDeleteSetsExecutionsToTerminated()
    {
        $user = $this->createUser();
        $job = $this->createJob($user, 'testDeleteSetsExecutionsToTerminated');

        $unknownExecution = $this->createExecution($job, 'testDeleteSetsExecutionsToTerminated#unknown', ExecutionStatus::UNKNOWN);
        $readyExecution = $this->createExecution($job, 'testDeleteSetsExecutionsToTerminated#ready', ExecutionStatus::READY);
        $runningExecution = $this->createExecution($job, 'testDeleteSetsExecutionsToTerminated#running', ExecutionStatus::RUNNING);
        $doneExecution = $this->createExecution($job, 'testDeleteSetsExecutionsToTerminated#done', ExecutionStatus::DONE);
        $stoppedExecution = $this->createExecution($job, 'testDeleteSetsExecutionsToTerminated#stopped', ExecutionStatus::STOPPED);
        $terminatedExecution = $this->createExecution($job, 'testDeleteSetsExecutionsToTerminated#terminated', ExecutionStatus::TERMINATED);
        $executions = [
            $unknownExecution->getId() => ExecutionStatus::TERMINATED,
            $readyExecution->getId() => ExecutionStatus::TERMINATED,
            $runningExecution->getId() => ExecutionStatus::TERMINATED,
            $doneExecution->getId() => ExecutionStatus::DONE,
            $stoppedExecution->getId() => ExecutionStatus::STOPPED,
            $terminatedExecution->getId() => ExecutionStatus::TERMINATED,
        ];

        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('DELETE', '/api/job', true, $tokenHeader, ['id' => $job->getId()]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
        /** @var Job $jobFromDatabase */
        $jobFromDatabase = $this->infrastructure->getRepository(Job::class)->find($job->getId());
        $this->assertEquals(JobStatus::DELETING, $jobFromDatabase->getStatus());

        foreach ($executions as $id => $expectedCode) {
            /** @var Execution $fromDb */
            $fromDb = $this->infrastructure->getRepository(Execution::class)->find($id);
            $this->assertEquals($expectedCode, $fromDb->getStatus());
        }
    }

    /**
     * @throws Exception
     */
    public function testCannotExecuteDeletingJob()
    {
        $user = $this->createUser();
        $job = $this->createJob($user, 'testCannotExecuteDeletingJob');
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('DELETE', '/api/job', true, $tokenHeader, ['id' => $job->getId()]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $name = sprintf('%s-deletion', __METHOD__);
        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute', $job->getId()), true, $tokenHeader, ['name' => $name]);
        $this->assertEquals(StatusCode::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getBody());
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
        $manager = (new Manager())
            ->setName('testname')
            ->setStatus(ManagerStatus::READY)
            ->setManagerToken('managertoken')
            ->setWorkerToken('ClusterToken')
            ->setIdByChoria('nodeId')
            ->setIp('1.2.3.55')
            ->setFqdn('manager1.manager.example.com');

        $job = (new Job())
            ->setType(JobType::BUSYBOX)
            ->setStatus(JobStatus::READY)
            ->setOwner($user)
            ->setName($name)
            ->setManager($manager)
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
