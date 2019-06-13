<?php

namespace Helio\Test\Integration;

use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Helio\Panel\Model\User;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
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
    public function setUp()
    {
        parent::setUp();

        $this->user = (new User())->setAdmin(1)->setName('testuser')->setCreated()->setEmail('test-autoscaler@example.com')->setActive(true);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->flush();
        $this->user->setToken(JwtUtility::generateUserIdentificationToken($this->user));
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->flush();

        $jobid = json_decode((string)$this->runApp('POST', '/api/job/add?jobid=_NEW&token=' . $this->user->getToken() . '&jobtype=' . JobType::ENERGY_PLUS_85, true, null)->getBody(), true)['id'];
        $command = str_replace('\\"', '"', ServerUtility::getLastExecutedShellCommand());
        $pattern = '/^.*"callback":"' . str_replace('/', '\\/', ServerUtility::getBaseUrl()) . '([^"]+)"/';
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
        $this->runApp('POST', $url, true, null, $this->callbackDataInit);

        // fake it till we make it: since we cannot query puppet for the manager-IP, we force it here.
        /** @var Job $job */
        $job = $this->jobRepository->findOneById($jobid);
        $this->runApp('POST', $url, true, null, $this->callbackDataManagerIp);
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush();

        // call callback again, this time the manager node is "ready"
        $this->runApp('POST', $url, true, null, $this->callbackDataRedundancy);


        ServerUtility::resetLastExecutedCommand();

        $this->job = $this->jobRepository->findOneById($jobid);

    }


    /**
     * @throws \Exception
     */
    public function testOnlyOneManagerNodesGetInitializedOnNewJob(): void
    {
        $result = json_decode((string)$this->runApp('POST', '/api/job/add?jobid=_NEW&token=' . $this->user->getToken() . '&jobtype=' . JobType::GITLAB_RUNNER, true, null)->getBody(), true);
        $this->assertArrayHasKey('id', $result, 'no ID of new job returned');
        $this->runApp('POST', '/api/job/add?token=' . $this->user->getToken(), true, null, ['jobid' => $result['id'], 'jobname' => 'testing 1551430509']);
        $this->assertNotNull($this->jobRepository->findOneByName('testing 1551430509')->getManagerNodes(), 'init node not ready, must not call to create redundant manager nodes yet');
    }

    /**
     * @throws \Exception
     */
    public function testRedundantManagersGetSetupOnCallbackCall(): void
    {
        $jobid = json_decode((string)$this->runApp('POST', '/api/job/add?jobid=_NEW&token=' . $this->user->getToken() . '&jobtype=' . JobType::GITLAB_RUNNER, true, null)->getBody(), true)['id'];
        $this->assertContains('manager-init-' . ServerUtility::getShortHashOfString($jobid), ServerUtility::getLastExecutedShellCommand());
        $command = str_replace('\\"', '"', ServerUtility::getLastExecutedShellCommand());
        $pattern = '/^.*"callback":"' . str_replace('/', '\\/', ServerUtility::getBaseUrl()) . '([^"]+)"/';
        $matches = [];
        preg_match($pattern, $command, $matches);
        $this->assertNotEmpty($matches);
        $url = '/' . $matches[1];

        $this->assertStringEndsWith('token=' . $this->user->getToken(), $url);

        // simulate provisioning call backs
        ServerUtility::resetLastExecutedCommand();
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand());
        $this->runApp('POST', $url, true, null, $this->callbackDataInit);
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand());
        $this->runApp('POST', $url, true, null, $this->callbackDataManagerIp);

        // TODO: Move these assertions below callbackDataRedundancy as soon as job is supposed to have redundant managers.
        //$this->assertContains('blah:manager', ServerUtility::getLastExecutedShellCommand(1));
        //$this->assertContains('manager-redundancy-' . ServerUtility::getShortHashOfString($jobid), ServerUtility::getLastExecutedShellCommand());
        //$this->assertContains('1.2.3.4:2345', ServerUtility::getLastExecutedShellCommand());
        //$this->assertContains('blah:worker', ServerUtility::getLastExecutedShellCommand());

        //$this->runApp('POST', $url, true, null, $this->callbackDataRedundancy);

        $job = $this->jobRepository->findOneById($jobid);
        $this->assertEquals(JobStatus::READY, $job->getStatus());
        $this->assertCount(1, $job->getManagerNodes());
    }


    /**
     * @throws \Exception
     */
    public function testReplicaGetAppliedOnNewTask(): void
    {
        $this->runApp('POST', '/exec', true, null, ['jobid' => $this->job->getId(), 'token' => $this->job->getToken()]);

        $this->assertContains('helio::queue', ServerUtility::getLastExecutedShellCommand());
        $this->assertContains('helio::task', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertContains('manager-init-' . ServerUtility::getShortHashOfString($this->job->getId()) . '.example.com', ServerUtility::getLastExecutedShellCommand(1));
    }


    /**
     * @throws \Exception
     */
    public function testReplicaDontGetAppliedTwiceOnTwoNewTasks(): void
    {
        $this->runApp('POST', '/exec', true, null, ['jobid' => $this->job->getId(), 'token' => $this->job->getToken()]);

        $this->assertContains('helio::queue', ServerUtility::getLastExecutedShellCommand());
        $this->assertContains('helio::task', ServerUtility::getLastExecutedShellCommand(1));

        ServerUtility::resetLastExecutedCommand();

        $this->runApp('POST', '/exec', true, null, ['jobid' => $this->job->getId(), 'token' => $this->job->getToken()]);
        $this->assertEquals('', ServerUtility::getLastExecutedShellCommand(), 'replicas shouldn\'t have changed, thus don\'t apply infrastrucutre again');
    }


    /**
     * @throws \Exception
     */
    public function testReplicaGetAdjustedOnManyNewTasks(): void
    {
        $i = 1 + JobFactory::getDispatchConfigOfJob($this->job)->getDispatchConfig()->getTaskPerReplica();
        do {
            --$i;
            $this->runApp('POST', '/exec', true, null, ['jobid' => $this->job->getId(), 'token' => $this->job->getToken()])->getBody();
        } while ($i > 0);

        $this->assertContains('helio::queue', ServerUtility::getLastExecutedShellCommand());
        $this->assertContains('helio::task', ServerUtility::getLastExecutedShellCommand(1));
    }


    /**
     * @throws \Exception
     */
    public function testJobStatusEndpointReflectsStatus(): void
    {


        $result = json_decode((string)$this->runApp('POST', '/api/job/add?jobid=_NEW&token=' . $this->user->getToken() . '&jobtype=' . JobType::GITLAB_RUNNER, true, null)->getBody(), true);
        $jobid = $result['id'];
        $jobtoken = $result['token'];

        $command = str_replace('\\"', '"', ServerUtility::getLastExecutedShellCommand());
        $pattern = '/^.*"callback":"' . str_replace('/', '\\/', ServerUtility::getBaseUrl()) . '([^"]+)"/';
        $matches = [];
        preg_match($pattern, $command, $matches);
        $this->assertNotEmpty($matches);
        $callbackUrl = '/' . $matches[1];

        $statusResult = $this->runApp('GET', "/api/job/isready?jobid=${jobid}&token=${jobtoken}");
        $this->assertEquals($statusResult->getStatusCode(), StatusCode::HTTP_FAILED_DEPENDENCY);


        $this->runApp('POST', $callbackUrl, true, null, $this->callbackDataInit);
        $this->assertEquals(StatusCode::HTTP_FAILED_DEPENDENCY, $statusResult->getStatusCode());

        $this->runApp('POST', $callbackUrl, true, null, $this->callbackDataManagerIp);
        $this->assertEquals(StatusCode::HTTP_FAILED_DEPENDENCY, $statusResult->getStatusCode());

        $this->runApp('POST', $callbackUrl, true, null, $this->callbackDataRedundancy);

        $statusResult = $this->runApp('GET', "/api/job/isready?jobid=${jobid}&token=${jobtoken}");

        $this->assertEquals(StatusCode::HTTP_OK, $statusResult->getStatusCode());

    }
}