<?php

namespace Helio\Test\Integration;

use Exception;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Manager;
use Helio\Panel\Model\Preferences\UserNotifications;
use Helio\Panel\Model\Preferences\UserPreferences;
use Helio\Panel\Model\User;
use Helio\Panel\Orchestrator\ManagerStatus;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Utility\NotificationUtility;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class ApiJobTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testThatIdParameterBehavesLikeJobIdOnJobApi()
    {
        $job = $this->createJob($this->createUser(), 'ApiJobTest');

        $statusResult = $this->runWebApp(
            'GET',
            '/api/job/isready?jobid=' . $job->getId(),
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $job)['token']]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode());

        $statusResult = $this->runWebApp(
            'GET',
            '/api/job/isready?id=' . $job->getId(),
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, null, null, $job)['token']]
        );
        $this->assertEquals(
            StatusCode::HTTP_OK,
            $statusResult->getStatusCode(),
            'on the job api, the parameter "id" should be synonym to "jobid".'
        );
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

        $statusResult = $this->runWebApp(
            'GET',
            '/api/job/isdone?id=' . $job->getId(),
            true,
            $header,
            ['billingReference' => $executionId]
        );
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
        $updateResponse = $this->runWebApp(
            'POST',
            '/api/job',
            true,
            $header,
            ['name' => 'testJobCreationAndUpdateWorks', 'type' => 'docker', 'id' => $job->getId()]
        );
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
        $body['limits'] = array_filter(
            $body['limits'],
            function ($key) {
                return in_array($key, ['running_jobs', 'running_executions']);
            },
            ARRAY_FILTER_USE_KEY
        );

        $this->assertEquals(
            [
                'success' => false,
                'message' => sprintf(
                    'Limit of running jobs reached. Amount running: %s / Limit: %s. Please contact helio support if you have any questions.',
                    $runningJobsLimit,
                    $runningJobsLimit
                ),
                'limits' => [
                    'running_jobs' => 1,
                    'running_executions' => 10,
                ],
            ],
            $body
        );
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
        $response = $this->runWebApp(
            'POST',
            sprintf('/api/job/%s/execute', $job->getId()),
            true,
            $header,
            ['name' => $name]
        );
        $this->assertEquals(StatusCode::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getBody());

        $body = json_decode((string) $response->getBody(), true);
        // notification is not interesting for the below equals check
        unset($body['notification']);
        $body['limits'] = array_filter(
            $body['limits'],
            function ($key) {
                return in_array($key, ['running_jobs', 'running_executions']);
            },
            ARRAY_FILTER_USE_KEY
        );

        $this->assertEquals(
            [
                'success' => false,
                'message' => sprintf(
                    'Limit of running executions reached. Amount running: %s / Limit: %s. Please contact helio support if you have any questions.',
                    $runningExecutionsLimit,
                    $runningExecutionsLimit
                ),
                'limits' => [
                    'running_jobs' => 5,
                    'running_executions' => 1,
                ],
            ],
            $body
        );
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

        $unknownExecution = $this->createExecution(
            $job,
            'testDeleteSetsExecutionsToTerminated#unknown',
            ExecutionStatus::UNKNOWN
        );
        $readyExecution = $this->createExecution(
            $job,
            'testDeleteSetsExecutionsToTerminated#ready',
            ExecutionStatus::READY
        );
        $runningExecution = $this->createExecution(
            $job,
            'testDeleteSetsExecutionsToTerminated#running',
            ExecutionStatus::RUNNING
        );
        $doneExecution = $this->createExecution(
            $job,
            'testDeleteSetsExecutionsToTerminated#done',
            ExecutionStatus::DONE
        );
        $stoppedExecution = $this->createExecution(
            $job,
            'testDeleteSetsExecutionsToTerminated#stopped',
            ExecutionStatus::STOPPED
        );
        $terminatedExecution = $this->createExecution(
            $job,
            'testDeleteSetsExecutionsToTerminated#terminated',
            ExecutionStatus::TERMINATED
        );
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
        $response = $this->runWebApp(
            'POST',
            sprintf('/api/job/%s/execute', $job->getId()),
            true,
            $tokenHeader,
            ['name' => $name]
        );
        $this->assertEquals(StatusCode::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getBody());
    }

    public function testSendJobReadyNotification()
    {
        $user = $this->createUser();
        $job = $this->createJob($user, 'testSendJobReadyNotification', JobStatus::INIT);
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/callback?id=%s', $job->getId()), true, $tokenHeader, []);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertCount(2, NotificationUtility::$mails);

        $userNotification = NotificationUtility::$mails[0];
        $adminNotification = NotificationUtility::$mails[1];

        $this->assertEquals(
            [
                'recipient' => 'test-autoscaler@example.com',
                'subject' => 'Job testSendJobReadyNotification (1) ready - Helio',
                'content' => "Hi testuser\n This is an automated notification from Helio.\n \n Your job with the id 1 is now ready to be executed on Helio",
                'from' => 'hello@idling.host',
            ],
            $userNotification
        );
        $this->assertEquals([
            'recipient' => 'team@helio.exchange',
            'subject' => 'Admin Notification from Panel',
            'content' => 'Job is now ready. By: test-autoscaler@example.com, type: busybox, id: 1, expected manager: manager1',
            'from' => 'hello@idling.host',
        ], $adminNotification);
    }

    public function testSendJobRemovedNotification()
    {
        $user = $this->createUser();
        $job = $this->createJob($user, 'testSendJobRemovedNotification', JobStatus::DELETING);

        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/callback?id=%s', $job->getId()), true, $tokenHeader, [
            'nodes' => [$job->getManager()->getName()],
            'deleted' => '1',
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertCount(1, NotificationUtility::$mails);

        $adminNotification = NotificationUtility::$mails[0];

        $this->assertEquals([
            'recipient' => 'team@helio.exchange',
            'subject' => 'Admin Notification from Panel',
            'content' => 'Job was deleted by test-autoscaler@example.com, type: busybox, id: 1, expected manager: manager-init-356a192b',
            'from' => 'hello@idling.host',
        ], $adminNotification);
    }

    public function testSendExecutionDoneNotification()
    {
        $user = $this->createUser();
        $job = $this->createJob($user, 'testSendExecutionDoneNotification', JobStatus::READY);
        $execution = $this->createExecution($job, 'testSendExecutionDoneNotification');
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult?id=%s', $job->getId(), $execution->getId()), true, $tokenHeader, []);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertCount(1, NotificationUtility::$mails);

        $userNotification = NotificationUtility::$mails[0];

        $this->assertEquals(
            [
                'recipient' => 'test-autoscaler@example.com',
                'subject' => 'Job testSendExecutionDoneNotification (1), Execution testSendExecutionDoneNotification (1) executed - Helio',
                'content' => "Hi testuser\n This is an automated notification from Helio.\n \n Your Job 1 with id 1 was successfully executed\nThe results can now be used.",
                'from' => 'hello@idling.host',
            ],
            $userNotification
        );
    }

    public function testDontSendKoalaFarmJobReadyNotification()
    {
        $user = $this->createUser(function (User $user) {
            $user->setOrigin(ServerUtility::get('KOALA_FARM_ORIGIN'));
        });

        $job = $this->createJob($user, 'testDontSendKoalaFarmJobReadyNotification', JobStatus::INIT);
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/callback?id=%s', $job->getId()), true, $tokenHeader, []);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertCount(1, NotificationUtility::$mails);

        $adminNotification = NotificationUtility::$mails[0];

        $this->assertEquals([
            'recipient' => 'team@helio.exchange',
            'subject' => 'Admin Notification from Panel',
            'content' => 'Job is now ready. By: test-autoscaler@example.com, type: busybox, id: 1, expected manager: manager1',
            'from' => 'hello@idling.host',
        ], $adminNotification);
    }

    public function testDontSendKoalaFarmJobRemovedNotification()
    {
        $user = $this->createUser(function (User $user) {
            $user->setOrigin(ServerUtility::get('KOALA_FARM_ORIGIN'));
        });
        $job = $this->createJob($user, 'testDontSendKoalaFarmJobRemovedNotification', JobStatus::DELETING);

        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/callback?id=%s', $job->getId()), true, $tokenHeader, [
            'nodes' => [$job->getManager()->getName()],
            'deleted' => '1',
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertCount(1, NotificationUtility::$mails);

        $adminNotification = NotificationUtility::$mails[0];

        $this->assertEquals([
            'recipient' => 'team@helio.exchange',
            'subject' => 'Admin Notification from Panel',
            'content' => 'Job was deleted by test-autoscaler@example.com, type: busybox, id: 1, expected manager: manager-init-356a192b',
            'from' => 'hello@idling.host',
        ], $adminNotification);
    }

    public function testSendKoalaFarmExecutionDoneNotification()
    {
        $user = $this->createUser(function (User $user) {
            $user->setOrigin(ServerUtility::get('KOALA_FARM_ORIGIN'));
            $notifications = (new UserNotifications())
                ->setEmailOnAllExecutionsEnded(true);
            $preferences = new UserPreferences();
            $preferences->setNotifications($notifications);

            $user->setPreferences($preferences);
        });

        $job = $this->createJob($user, 'testSendKoalaFarmExecutionDoneNotification', JobStatus::READY);
        $execution = $this->createExecution($job, 'testSendKoalaFarmExecutionDoneNotification');
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user, null, $job)['token']];

        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute/submitresult?id=%s', $job->getId(), $execution->getId()), true, $tokenHeader, []);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), $response->getBody()->getContents());

        $this->assertCount(1, NotificationUtility::$mails);

        $userNotification = NotificationUtility::$mails[0];

        $this->assertEquals(
            [
                'recipient' => 'test-autoscaler@example.com',
                'subject' => 'Rendering completed! - Koala Farm',
                'content' => "Hi testuser\n Thanks for using Koala farm!\n \n A new render completed successfully! Please visit http://localhost:3000 to download the results.",
                'from' => 'hello@koala.farm',
            ],
            $userNotification
        );
    }

    public function testSetReplicaOneInitially()
    {
        $repository = $this->infrastructure->getRepository(Execution::class);

        $user = $this->createUser(function (User $user) {
            $user->setOrigin(ServerUtility::get('KOALA_FARM_ORIGIN'));
        });
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];

        $job = $this->createJob(
            $user,
            'testSetReplicaOneInitially',
            JobStatus::READY,
            function (Job $job) {
                $job->setLabels(['render']);
                $job->setType(JobType::BLENDER);
            }
        );

        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute', $job->getId()), true, $tokenHeader, [
            'name' => 'testSetReplicaOneInitially',
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals(1, $repository->find($body['id'])->getReplicas());
    }

    public function testSlidingWindowDoesNotAffectOtherJobTypes()
    {
        $repository = $this->infrastructure->getRepository(Execution::class);

        $user = $this->createUser(function (User $user) {
        });
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $user)['token']];

        $job = $this->createJob(
            $user,
            'testSlidingWindowDoesNotAffectOtherJobTypes',
            JobStatus::READY
        );

        $response = $this->runWebApp('POST', sprintf('/api/job/%s/execute', $job->getId()), true, $tokenHeader, [
            'name' => 'testSlidingWindowDoesNotAffectOtherJobTypes',
        ]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals(null, $repository->find($body['id'])->getReplicas());
    }

    /**
     * @param  callable  $options optional changes to the provided user object before persisting
     * @return User
     * @throws Exception
     */
    private function createUser(?callable $options = null): User
    {
        /** @var User $user */
        $user = (new User())
            ->setAdmin(1)
            ->setName('testuser')
            ->setEmail(
                'test-autoscaler@example.com'
            )->setActive(true)->setCreated();

        if ($options) {
            $options($user);
        }

        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush($user);

        return $user;
    }

    private function createJob(User $user, string $name = __CLASS__, int $status = JobStatus::READY, ?callable $options = null): Job
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
            ->setStatus($status)
            ->setOwner($user)
            ->setName($name)
            ->setManager($manager)
            ->setCreated();

        if ($options) {
            $options($job);
        }

        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush($job);

        return $job;
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
