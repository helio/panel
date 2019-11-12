<?php

namespace Helio\Test\Integration;

use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Manager;
use Helio\Panel\Model\Preferences\UserLimits;
use Helio\Panel\Model\Preferences\UserPreferences;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Helper\TestHelper;
use Helio\Test\Infrastructure\Orchestrator\OrchestratorFactory;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class ManagerNodesTest extends TestCase
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
     * @var array
     */
    protected $data = [];

    /**
     * @var array
     */
    protected $callbackData;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->user = (new User())
            ->setAdmin(1)
            ->setName('testuser')
            ->setCreated()
            ->setEmail('test-autoscaler@example.com')
            ->setActive(true);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('POST',
            '/api/job',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
            ['type' => JobType::ENERGY_PLUS_85, 'name' => sprintf('ManagerNodesTest - %s', $this->getName())]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $body = (string) $response->getBody();
        $jobId = \GuzzleHttp\json_decode($body, true)['id'];

        $url = TestHelper::getCallbackUrlFromExecutedShellCommand();

        $this->callbackData = ['nodes' => [
            'manager-init-' . ServerUtility::getShortHashOfString($jobId) . '-1.example.com',
        ], 'swarm_token_manager' => 'blah:manager', 'swarm_token_worker' => 'blah:worker', 'manager_id' => 'ladida', 'manager_ip' => '1.2.3.4:884'];

        // simulate provisioning call backs
        $response = $this->runWebApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackData);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), $url);

        ServerUtility::resetLastExecutedCommand();
        OrchestratorFactory::resetInstances();

        $this->job = $this->jobRepository->find($jobId);
    }

    /**
     * @throws \Exception
     */
    public function testRedundantManagersGetSetupOnCallbackCall(): void
    {
        $response = $this->runWebApp(
            'POST',
            '/api/job',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
            ['type' => JobType::GITLAB_RUNNER, 'name' => $this->getName()]
        );
        $jobId = json_decode((string) $response->getBody(), true)['id'];

        /** @var Job $job */
        $job = $this->jobRepository->find($jobId);
        $this->assertFalse($job->getManager()->works());
        $this->assertStringContainsString('manager-init-', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('user_id', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString($this->user->getId(), ServerUtility::getLastExecutedShellCommand());

        $url = TestHelper::getCallbackUrlFromExecutedShellCommand();

        $this->assertStringNotContainsString('token=', $url);

        // simulate provisioning call backs
        ServerUtility::resetLastExecutedCommand();
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand());
        $this->runWebApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackData);

        $job = $this->jobRepository->find($jobId);
        $this->assertEquals(JobStatus::READY, $job->getStatus());
        $this->assertInstanceOf(Manager::class, $job->getManager());
    }

    /**
     * @throws \Exception
     */
    public function testReplicaGetAppliedOnNewExecution(): void
    {
        $response = $this->runWebApp('POST',
            '/api/job/' . $this->job->getId() . '/execute',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
            ['name' => $this->getName()]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $executions = $this->executionRepository->findBy(['job' => $this->job->getId()]);
        $this->assertIsArray($executions);
        $this->assertCount(1, $executions);

        $serviceCreateCmd = ServerUtility::getLastExecutedShellCommand();
        $this->assertStringContainsString('helio::cluster::services::create', $serviceCreateCmd);
        $this->assertStringContainsString('\"service\":\"ep85-1-1\"', $serviceCreateCmd);
        $this->assertStringContainsString('\"replicas\":1', $serviceCreateCmd);
        $this->assertStringContainsString('\"image\":\"hub.helio.dev:4567\/helio\/runner\/ep85:latest\"', $serviceCreateCmd);
    }

    /**
     * @throws \Exception
     */
    public function testReplicaDontGetAppliedTwiceOnTwoNewExecutions(): void
    {
        $response = $this->runWebApp('POST',
            '/api/job/' . $this->job->getId() . '/execute',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
            ['name' => $this->getName()]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertStringContainsString('helio::cluster::services::create', ServerUtility::getLastExecutedShellCommand());

        ServerUtility::resetLastExecutedCommand();

        $this->runWebApp('POST', '/api/job/' . $this->job->getId() . '/execute', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);
        $this->assertEquals('', ServerUtility::getLastExecutedShellCommand(), 'replicas shouldn\'t have changed, thus don\'t apply infrastrucutre again');
    }

    /**
     * @throws \Exception
     */
    public function testReplicaGetAppliedOnTwoNewExecutionsForFixedReplicaJob(): void
    {
        $this->job->setType(JobType::DOCKER);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('POST',
            '/api/job/' . $this->job->getId() . '/execute',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
            ['name' => $this->getName()]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertStringContainsString('helio::cluster::services::create', ServerUtility::getLastExecutedShellCommand());

        ServerUtility::resetLastExecutedCommand();

        $response = $this->runWebApp('POST',
            '/api/job/' . $this->job->getId() . '/execute',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
            ['name' => $this->getName() . '#2']
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('helio::cluster::services::create', ServerUtility::getLastExecutedShellCommand());

        $executions = $this->executionRepository->findBy(['job' => $this->job, 'name' => $this->getName() . '#2']);
        $serviceCreateCmd = ServerUtility::getLastExecutedShellCommand();
        /** @var Execution $execution */
        foreach ($executions as $execution) {
            $this->assertStringContainsString('helio::cluster::services::create', $serviceCreateCmd);
            $this->assertStringContainsString(sprintf('\"service\":\"%s\"', $execution->getServiceName()), $serviceCreateCmd);
            $this->assertStringContainsString('\"replicas\":1', $serviceCreateCmd);
            $this->assertStringContainsString('\"image\":\"hello-world\"', $serviceCreateCmd);
        }
    }

    /**
     * @throws \Exception
     */
    public function testReplicaGetToZeroAfterHavingRunFixedReplicaCountJob(): void
    {
        $this->job->setType(JobType::DOCKER);
        $this->user->setAdmin(true);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('POST',
            '/api/job/' . $this->job->getId() . '/execute',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
            ['name' => $this->getName()]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $body = \GuzzleHttp\json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('id', $body);

        $response = $this->runWebApp('POST',
            '/api/job/' . $this->job->getId() . '/execute/submitresult?id=' . $body['id'],
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
            ['result' => 42]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), $response->getBody());
    }

    /**
     * @throws \Exception
     */
    public function testReplicaGetAdjustedOnManyNewExecutions(): void
    {
        $i = 1 + JobFactory::getDispatchConfigOfJob($this->job)->getDispatchConfig()->getExecutionPerReplica();
        do {
            --$i;
            $response = $this->runWebApp('POST',
                '/api/job/' . $this->job->getId() . '/execute',
                true,
                ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
                ['name' => sprintf('%s-%s', $this->getName(), $i)]
            );
            $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), (string) $response->getBody());
        } while ($i > 0);

        $this->assertStringContainsString('helio::cluster::services::create', ServerUtility::getLastExecutedShellCommand());
    }

    /**
     * @throws \Exception
     */
    public function testJobStatusEndpointReflectsStatus(): void
    {
        $result = $this->runWebApp('POST',
            '/api/job',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
            ['type' => JobType::GITLAB_RUNNER, 'name' => $this->getName()]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $result->getStatusCode());
        $body = json_decode((string) $result->getBody(), true);
        $this->assertNotNull($body);
        $jobid = $body['id'];
        $jobtoken = $body['token'];

        $callbackUrl = TestHelper::getCallbackUrlFromExecutedShellCommand();

        $response = $this->runWebApp('GET', "/api/job/isready?jobid=${jobid}", true, ['Authorization' => 'Bearer ' . $jobtoken]);
        $this->assertEquals($response->getStatusCode(), StatusCode::HTTP_FAILED_DEPENDENCY);

        $response = $this->runWebApp('POST', $callbackUrl, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackData, null);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $response = $this->runWebApp('GET', "/api/job/isready?jobid=${jobid}", true, ['Authorization' => 'Bearer ' . $jobtoken]);

        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    public function testManagerNodeGetsRecreatedOnNewExecutionOfPausedJob(): void
    {
        $this->job->setStatus(JobStatus::READY_PAUSED);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->runWebApp('POST',
            sprintf('/api/job/%s/execute', $this->job->getId()),
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user, null, $this->job)['token']],
            ['type' => JobType::GITLAB_RUNNER, 'name' => $this->getName()]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $result->getStatusCode());

        $this->assertStringContainsString('helio::cluster::services::create', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('infrastructure::gce::create', ServerUtility::getLastExecutedShellCommand(1));
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    public function testManagerNodeDoesNotGetRecreatedOnNewExecutionOfRunningJob(): void
    {
        $result = $this->runWebApp('POST',
            sprintf('/api/job/%s/execute', $this->job->getId()),
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user, null, $this->job)['token']],
            ['type' => JobType::GITLAB_RUNNER, 'name' => $this->getName()]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $result->getStatusCode());
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand(2));

        $this->assertStringContainsString('helio::cluster::services::create', ServerUtility::getLastExecutedShellCommand());
    }

    public function testJobUpdateWhenMultipleJobsOnSameManager(): void
    {
        $limits = new UserLimits();
        $limits->setManagerNodes([$this->job->getManager()->getFqdn()]);

        $preferences = new UserPreferences();
        $preferences->setLimits($limits);

        $restrictedUser = (new User())
            ->setPreferences($preferences)
            ->setName('testuser2')
            ->setCreated()
            ->setEmail('test-jobexec@example.com')
            ->setActive(true);
        $this->infrastructure->getEntityManager()->persist($restrictedUser);
        $this->infrastructure->getEntityManager()->flush();

        $response = $this->runWebApp('POST',
            '/api/job',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $restrictedUser)['token']],
            ['type' => JobType::ENERGY_PLUS_85, 'name' => sprintf('ManagerNodesTest - %s', $this->getName())]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), $response->getBody()->getContents());

        $body = \GuzzleHttp\json_decode($response->getBody(), true);
        $jobId = $body['id'];
        $jobToken = $body['token'];

        $response = $this->runWebApp('GET', "/api/job/isready?jobid=${jobId}", true, ['Authorization' => 'Bearer ' . $jobToken]);

        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $response = $this->runWebApp('POST',
            '/api/job/' . $jobId . '/execute',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $restrictedUser)['token']],
            ['name' => __METHOD__]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), (string) $response->getBody());

        $this->assertStringContainsString('helio::job::update', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('ids\":\"' . implode(',', [$this->job->getId(), $jobId]) . '\"', ServerUtility::getLastExecutedShellCommand());

        $this->assertStringContainsString('helio::cluster::services::create', ServerUtility::getLastExecutedShellCommand(1));
    }
}
