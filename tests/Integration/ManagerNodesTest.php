<?php

namespace Helio\Test\Integration;

use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
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
    protected $callbackDataInit;

    /**
     * @var array
     */
    protected $callbackDataManagerIp;

    /**
     * @var array
     */
    protected $callbackDataRedundancy;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->user = (new User())->setAdmin(1)->setName('testuser')->setCreated()->setEmail('test-autoscaler@example.com')->setActive(true);
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

        $this->callbackDataInit = ['nodes' => 'manager-init-' . ServerUtility::getShortHashOfString($jobId) . '.example.com', 'swarm_token_manager' => 'blah:manager', 'swarm_token_worker' => 'blah:worker'];
        $this->callbackDataManagerIp = ['manager_ip' => '1.2.3.4:2345'];
        $this->callbackDataRedundancy = ['nodes' => [
            'manager-redundancy-' . ServerUtility::getShortHashOfString($jobId) . '-1.example.com',
            'manager-redundancy-' . ServerUtility::getShortHashOfString($jobId) . '-2.example.com',
        ], 'docker_token' => 'blah'];

        // simulate provisioning call backs
        $response = $this->runWebApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataInit);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode(), $url);

        // fake it till we make it: since we cannot query puppet for the manager-IP, we force it here.
        /** @var Job $job */
        $job = $this->jobRepository->find($jobId);
        $response = $this->runWebApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataManagerIp);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush();

        // call callback again, this time the manager node is "ready"
        $response = $this->runWebApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataRedundancy);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        ServerUtility::resetLastExecutedCommand();
        OrchestratorFactory::resetInstances();

        $this->job = $this->jobRepository->find($jobId);
    }

    /**
     * @throws \Exception
     */
    public function testOnlyOneManagerNodesGetInitializedOnNewJob(): void
    {
        $response = $this->runWebApp(
            'POST',
            '/api/job',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token'], 'Content-Type' => 'application/json'],
            ['type' => JobType::GITLAB_RUNNER, 'name' => $this->getName()]
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $result = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('id', $result, 'no ID of new job returned');

        $response = $this->runWebApp('POST',
            '/api/job',
            true,
            ['Authorization' => 'Bearer ' . $result['token'], 'Content-Type' => 'application/json'],
            ['id' => $result['id'], 'name' => 'testing 1551430509']
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $this->assertNotNull($this->jobRepository->findOneBy(['name' => 'testing 1551430509'])->getManagerNodes(), 'init node not ready, must not call to create redundant manager nodes yet');
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
        $this->assertCount(0, $job->getManagerNodes());
        $this->assertStringContainsString('manager-init-' . ServerUtility::getShortHashOfString($jobId), ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('user_id', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString($this->user->getId(), ServerUtility::getLastExecutedShellCommand());

        $url = TestHelper::getCallbackUrlFromExecutedShellCommand();

        $this->assertStringNotContainsString('token=', $url);

        // simulate provisioning call backs
        ServerUtility::resetLastExecutedCommand();
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand());
        $this->runWebApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataInit);
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand());
        $this->runWebApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataManagerIp);

        // TODO: Move these assertions below callbackDataRedundancy as soon as job is supposed to have redundant managers.
        //$this->assertContains('blah:manager', ServerUtility::getLastExecutedShellCommand(1));
        //$this->assertContains('manager-redundancy-' . ServerUtility::getShortHashOfString($jobId), ServerUtility::getLastExecutedShellCommand());
        //$this->assertContains('1.2.3.4:2345', ServerUtility::getLastExecutedShellCommand());
        //$this->assertContains('blah:worker', ServerUtility::getLastExecutedShellCommand());

        //$this->runApp('POST', $url, true, null, $this->callbackDataRedundancy);

        $job = $this->jobRepository->find($jobId);
        $this->assertEquals(JobStatus::READY, $job->getStatus());
        $this->assertCount(1, $job->getManagerNodes());
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

        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('helio::task::update', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('task_ids', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('[' . $executions[0]->getId() . ']', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('manager-init-' . ServerUtility::getShortHashOfString($this->job->getId()) . '.example.com', ServerUtility::getLastExecutedShellCommand(1));
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

        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('helio::task', ServerUtility::getLastExecutedShellCommand(1));

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

        $this->assertStringContainsString('helio::task', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());

        ServerUtility::resetLastExecutedCommand();

        $response = $this->runWebApp('POST',
            '/api/job/' . $this->job->getId() . '/execute',
            true,
            ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']],
            ['name' => $this->getName() . '#2']
        );
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('helio::task', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());

        $executions = $this->executionRepository->findBy(['job' => $this->job]);
        $ids = '';
        foreach ($executions as $execution) {
            /* @var Execution $execution */
            $ids .= $execution->getId() . ',';
        }
        $ids = rtrim($ids, ',');
        $this->assertStringContainsString('[' . $ids . ']', ServerUtility::getLastExecutedShellCommand(1));
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

        $response = $this->runWebApp('POST',
            '/api/job/' . $this->job->getId() . '/execute/submitresult',
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

        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('helio::task', ServerUtility::getLastExecutedShellCommand(1));
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

        $response = $this->runWebApp('POST', $callbackUrl, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataInit, null);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $response = $this->runWebApp('POST', $callbackUrl, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataManagerIp, null);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $response = $this->runWebApp('POST', $callbackUrl, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataRedundancy, null);
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
        $this->assertStringContainsString('infrastructure::gce::create', ServerUtility::getLastExecutedShellCommand(2));
        $this->assertStringContainsString('helio::task::update', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());
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
        $this->assertStringContainsString('helio::task::update', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());
    }
}
