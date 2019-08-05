<?php

namespace Helio\Test\Integration;

use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\User;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
use Helio\Test\Unit\JobTest;
use OpenApi\Annotations\Server;
use Psr\Http\Message\ResponseInterface;
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

        $response = $this->runApp('POST', '/api/job?jobtype=' . JobType::ENERGY_PLUS_85, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);

        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        $body = (string)$response->getBody();
        $jobid = json_decode($body, true)['id'];
        $command = str_replace('\\"', '"', ServerUtility::getLastExecutedShellCommand());
        $pattern = '/^.*\\\\"callback\\\\":\\\\"' . str_replace('/', '\\/', ServerUtility::getBaseUrl()) . '([^"]+)\\\\"/';
        $matches = [];
        preg_match($pattern, $command, $matches);
        $this->assertNotEmpty($matches);
        $url = '/' . $matches[1];

        $this->callbackDataInit = ['nodes' => 'manager-init-' . ServerUtility::getShortHashOfString($jobid) . '.example.com', 'swarm_token_manager' => 'blah:manager', 'swarm_token_worker' => 'blah:worker'];
        $this->callbackDataManagerIp = ['manager_ip' => '1.2.3.4:2345'];
        $this->callbackDataRedundancy = ['nodes' => [
            'manager-redundancy-' . ServerUtility::getShortHashOfString($jobid) . '-1.example.com',
            'manager-redundancy-' . ServerUtility::getShortHashOfString($jobid) . '-2.example.com'
        ], 'docker_token' => 'blah'];


        // simulate provisioning call backs
        $response = $this->runApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataInit);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        // fake it till we make it: since we cannot query puppet for the manager-IP, we force it here.
        /** @var Job $job */
        $job = $this->jobRepository->find($jobid);
        $response = $this->runApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataManagerIp);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush();

        // call callback again, this time the manager node is "ready"
        $response = $this->runApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataRedundancy);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());


        ServerUtility::resetLastExecutedCommand();

        $this->job = $this->jobRepository->find($jobid);

    }


    /**
     * @throws \Exception
     */
    public function testOnlyOneManagerNodesGetInitializedOnNewJob(): void
    {
        $result = json_decode((string)$this->runApp('POST', '/api/job?jobtype=' . JobType::GITLAB_RUNNER, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], null)->getBody(), true);
        $this->assertArrayHasKey('id', $result, 'no ID of new job returned');
        $this->runApp('POST', '/api/job', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], ['jobid' => $result['id'], 'jobname' => 'testing 1551430509']);
        $this->assertNotNull($this->jobRepository->findOneBy(['name' => 'testing 1551430509'])->getManagerNodes(), 'init node not ready, must not call to create redundant manager nodes yet');
    }

    /**
     * @throws \Exception
     */
    public function testRedundantManagersGetSetupOnCallbackCall(): void
    {
        $jobid = json_decode((string)$this->runApp('POST', '/api/job?jobtype=' . JobType::GITLAB_RUNNER, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']])->getBody(), true)['id'];
        /** @var Job $job */
        $job = $this->jobRepository->find($jobid);
        $this->assertCount(0, $job->getManagerNodes());
        $this->assertStringContainsString('manager-init-' . ServerUtility::getShortHashOfString($jobid), ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('user_id', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString($this->user->getId(), ServerUtility::getLastExecutedShellCommand());
        $command = str_replace('\\"', '"', ServerUtility::getLastExecutedShellCommand());
        $pattern = '/^.*\\\\"callback\\\\":\\\\"' . str_replace('/', '\\/', ServerUtility::getBaseUrl()) . '([^"]+)\\\\"/';
        $matches = [];
        preg_match($pattern, $command, $matches);
        $this->assertNotEmpty($matches);
        $url = '/' . $matches[1];

        $this->assertStringNotContainsString('token=', $url);

        // simulate provisioning call backs
        ServerUtility::resetLastExecutedCommand();
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand());
        $this->runApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataInit);
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand());
        $this->runApp('POST', $url, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataManagerIp);

        // TODO: Move these assertions below callbackDataRedundancy as soon as job is supposed to have redundant managers.
        //$this->assertContains('blah:manager', ServerUtility::getLastExecutedShellCommand(1));
        //$this->assertContains('manager-redundancy-' . ServerUtility::getShortHashOfString($jobid), ServerUtility::getLastExecutedShellCommand());
        //$this->assertContains('1.2.3.4:2345', ServerUtility::getLastExecutedShellCommand());
        //$this->assertContains('blah:worker', ServerUtility::getLastExecutedShellCommand());

        //$this->runApp('POST', $url, true, null, $this->callbackDataRedundancy);

        $job = $this->jobRepository->find($jobid);
        $this->assertEquals(JobStatus::READY, $job->getStatus());
        $this->assertCount(1, $job->getManagerNodes());
    }


    /**
     * @throws \Exception
     */
    public function testReplicaGetAppliedOnNewExecution(): void
    {
        $this->runApp('POST', '/api/job/' . $this->job->getId() . '/execute', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);
        $executions = $this->executionRepository->findBy(['job' => $this->job->getId()]);
        $this->assertIsArray($executions);
        $this->assertCount(1, $executions);

        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('helio::execution::update', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('execution_ids', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('[' . $executions[0]->getId() . ']', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('manager-init-' . ServerUtility::getShortHashOfString($this->job->getId()) . '.example.com', ServerUtility::getLastExecutedShellCommand(1));
    }


    /**
     * @throws \Exception
     */
    public function testReplicaDontGetAppliedTwiceOnTwoNewExecutions(): void
    {
        $this->runApp('POST', '/api/job/' . $this->job->getId() . '/execute', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);

        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('helio::execution', ServerUtility::getLastExecutedShellCommand(1));

        ServerUtility::resetLastExecutedCommand();

        $this->runApp('POST', '/api/job/' . $this->job->getId() . '/execute', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);
        $this->assertEquals('', ServerUtility::getLastExecutedShellCommand(), 'replicas shouldn\'t have changed, thus don\'t apply infrastrucutre again');
    }

    /**
     * @throws \Exception
     */
    public function testReplicaGetAppliedOnTwoNewExecutionsForFixedReplicaJob(): void
    {
        $this->job->setType(JobType::VF_DOCKER);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->flush();

        $this->runApp('POST', '/api/job/' . $this->job->getId() . '/execute', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);

        $this->assertStringContainsString('helio::execution', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());

        ServerUtility::resetLastExecutedCommand();

        $this->runApp('POST', '/api/job/' . $this->job->getId() . '/execute', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);
        $this->assertStringContainsString('helio::execution', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());

        $executions = $this->executionRepository->findAll();
        $ids = '';
        foreach ($executions as $execution) {
            /** @var Execution $execution */
            $ids .= $execution->getId() . ',';
        }
        $ids = rtrim($ids, ',');
        $this->assertStringContainsString('[' . $ids . ']', ServerUtility::getLastExecutedShellCommand(1));
    }


    /**
     * @throws \Exception
     */
    public function testReplicaGetAdjustedOnManyNewExecutions(): void
    {
        $i = 1 + JobFactory::getDispatchConfigOfJob($this->job)->getDispatchConfig()->getExecutionPerReplica();
        do {
            --$i;
            $this->runApp('POST', '/api/job/' . $this->job->getId() . '/execute', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);
        } while ($i > 0);

        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('helio::execution', ServerUtility::getLastExecutedShellCommand(1));
    }


    /**
     * @throws \Exception
     */
    public function testJobStatusEndpointReflectsStatus(): void
    {


        $result = $this->runApp('POST', '/api/job?jobtype=' . JobType::GITLAB_RUNNER, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);
        $this->assertEquals(StatusCode::HTTP_OK, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertNotNull($body);
        $jobid = $body['id'];
        $jobtoken = $body['token'];

        $command = str_replace('\\"', '"', ServerUtility::getLastExecutedShellCommand());
        $pattern = '/^.*\\\\"callback\\\\":\\\\"' . str_replace('/', '\\/', ServerUtility::getBaseUrl()) . '([^"]+)\\\\"/';
        $matches = [];
        preg_match($pattern, $command, $matches);
        $this->assertNotEmpty($matches);
        $callbackUrl = '/' . $matches[1];

        $statusResult = $this->runApp('GET', "/api/job/isready?jobid=${jobid}", true, ['Authorization' => 'Bearer ' . $jobtoken]);
        $this->assertEquals($statusResult->getStatusCode(), StatusCode::HTTP_FAILED_DEPENDENCY);


        $this->runApp('POST', $callbackUrl, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataInit, null);
        $this->assertEquals(StatusCode::HTTP_FAILED_DEPENDENCY, $statusResult->getStatusCode());

        $this->runApp('POST', $callbackUrl, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataManagerIp, null);
        $this->assertEquals(StatusCode::HTTP_FAILED_DEPENDENCY, $statusResult->getStatusCode());

        $this->runApp('POST', $callbackUrl, true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], $this->callbackDataRedundancy, null);

        $statusResult = $this->runApp('GET', "/api/job/isready?jobid=${jobid}", true, ['Authorization' => 'Bearer ' . $jobtoken]);

        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode());
    }
}