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
use Psr\Http\Message\ResponseInterface;

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
        $pattern = '/^.*"uri":"' . str_replace('/', '\\/', ServerUtility::getBaseUrl()) . '([^"]+)"/';
        $matches = [];
        preg_match($pattern, $command, $matches);
        $this->assertNotEmpty($matches);
        $url = '/' . $matches[1];

        $this->runApp('POST', $url, true);

        // fake it till we make it: since we cannot query puppet for the manager-IP, we force it here.
        /** @var Job $job */
        $job = $this->jobRepository->findOneById($jobid);
        $job->setInitManagerIp('1.2.3.4');
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush();

        // call callback again, this time the manager node is "ready"
        $this->runApp('POST', $url, true);

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
        $this->assertCount(1, $this->jobRepository->findOneByName('testing 1551430509')->getManagerNodes(), 'init node not ready, must not call to create redundant manager nodes yet');
    }

    /**
     * @throws \Exception
     */
    public function testRedundantManagersGetSetupOnCallbackCall(): void
    {
        $jobid = json_decode((string)$this->runApp('POST', '/api/job/add?jobid=_NEW&token=' . $this->user->getToken() . '&jobtype=' . JobType::GITLAB_RUNNER, true, null)->getBody(), true)['id'];
        $this->assertContains('manager-' . ServerUtility::getShortHashOfString($jobid) . '-0', ServerUtility::getLastExecutedShellCommand());
        $command = str_replace('\\"', '"', ServerUtility::getLastExecutedShellCommand());
        $pattern = '/^.*"uri":"' . str_replace('/', '\\/', ServerUtility::getBaseUrl()) . '([^"]+)"/';
        $matches = [];
        preg_match($pattern, $command, $matches);
        $this->assertNotEmpty($matches);
        $url = '/' . $matches[1];

        $this->assertStringEndsWith('token=' . $this->user->getToken(), $url);
        $this->runApp('POST', $url, true);

        $this->assertContains('Addr', ServerUtility::getLastExecutedShellCommand(), 'should query for manager node IP address here 1551435953');
        $this->assertContains(ServerUtility::getShortHashOfString($jobid) . '-0', ServerUtility::getLastExecutedShellCommand());

        // fake it till we make it: since we cannot query puppet for the manager-IP, we force it here.
        /** @var Job $job */
        $job = $this->jobRepository->findOneById($jobid);
        $this->assertNotNull($job);
        $this->assertNotEquals(JobStatus::READY, $job->getStatus());
        $job->setInitManagerIp('1.2.3.4');
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush();

        // call callback again, this time the manager node is "ready"
        $this->runApp('POST', $url, true);

        $job = $this->jobRepository->findOneById($jobid);
        $this->assertEquals(JobStatus::READY, $job->getStatus());
        $this->assertCount(3, $job->getManagerNodes());
        $this->assertNotContains('manager-' . ServerUtility::getShortHashOfString($jobid) . '-0', ServerUtility::getLastExecutedShellCommand());
        $this->assertContains('manager-' . ServerUtility::getShortHashOfString($jobid) . '-1', ServerUtility::getLastExecutedShellCommand());
        $this->assertContains('manager-' . ServerUtility::getShortHashOfString($jobid) . '-2', ServerUtility::getLastExecutedShellCommand());
    }


    /**
     * @throws \Exception
     */
    public function testReplicaGetAppliedOnNewTask(): void
    {
        $this->runApp('POST', '/exec', true, null, ['jobid' => $this->job->getId(), 'token' => $this->job->getToken()]);
        $this->assertContains('ssh', ServerUtility::getLastExecutedShellCommand());
        $this->assertContains('docker::swarm_token', ServerUtility::getLastExecutedShellCommand());
    }


    /**
     * @throws \Exception
     */
    public function testReplicaDontGetAppliedTwiceOnTwoNewTasks(): void
    {
        $this->runApp('POST', '/exec', true, null, ['jobid' => $this->job->getId(), 'token' => $this->job->getToken()]);
        $this->assertContains('ssh', ServerUtility::getLastExecutedShellCommand());
        $this->assertContains('docker::swarm_token', ServerUtility::getLastExecutedShellCommand());

        ServerUtility::resetLastExecutedCommand();

        $this->runApp('POST', '/exec', true, null, ['jobid' => $this->job->getId(), 'token' => $this->job->getToken()]);
        $this->assertEquals('', ServerUtility::getLastExecutedShellCommand(), 'replicas shouldn\'t have changed, thus don\'t apply infrastrucutre again');
    }


    /**
     * @throws \Exception
     */
    public function testReplicaGetAdjustedOnManyNewTasks(): void
    {
        $i = 1 + JobFactory::getDispatchConfigOfJob($this->job)->getDispatchConfig()->getTaskCountPerReplica();
        do {
            --$i;
            $result = (string)$this->runApp('POST', '/exec', true, null, ['jobid' => $this->job->getId(), 'token' => $this->job->getToken()])->getBody();
        } while ($i > 0);

        $this->assertContains('ssh', ServerUtility::getLastExecutedShellCommand());
        $this->assertContains('docker::swarm_token', ServerUtility::getLastExecutedShellCommand());
    }
}