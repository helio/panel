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

class AutoscalerTest extends TestCase
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
     * @var Instance
     */
    protected $instance;


    /**
     * @var array
     */
    protected $data = [];


    /**
     * @var string
     */
    protected $url = '/api/admin/getJobHiera';


    /**
     * @throws \Exception
     */
    public function setUp()
    {
        parent::setUp();
        ServerUtility::resetLastExecutedCommand();
        $this->instance = (new Instance())->setName('testinstance')->setCreated()->setFqdn('testserver.example.com')->setStatus(InstanceStatus::RUNNING);

        $this->user = (new User())->setAdmin(1)->setName('testuser')->setCreated()->setEmail('test-autoscaler@example.com')->setActive(true)->addInstance($this->instance);
        $this->job = (new Job())->setStatus(JobStatus::READY)->setType(JobType::ENERGY_PLUS_85)->setOwner($this->user)->setInitManagerIp('1.1.1.1')->setManagerNodes(['1', '2', '3']);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->flush();
        $this->user->setToken(JwtUtility::generateUserIdentificationToken($this->user));
        $this->job->setToken(JwtUtility::generateJobIdentificationToken($this->job));
        $this->user->addJob($this->job);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->flush();


        $this->url .= '?token=' . $this->user->getToken() . '&jobid=' . $this->job->getId();

    }

    /**
     * @return ResponseInterface
     * @throws \Exception
     */
    protected function exec(): ResponseInterface
    {
        return $this->runApp('GET', $this->url, true);
    }

    /**
     * @param ResponseInterface $response
     * @param string $key
     * @return mixed|string
     */
    protected function findValueOfKeyInHiera(ResponseInterface $response, string $key)
    {
        $matches = [];
        preg_match("/\W*'?$key'?:\s*(\S+)/", (string)$response->getBody(), $matches);
        return $matches[1] ?? '';
    }


    /**
     * @throws \Exception
     */
    public function testNoReplicasOnEmptyJob(): void
    {
        $result = $this->exec();
        $replicas = $this->findValueOfKeyInHiera($result, 'replicas');
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(0, $replicas);
    }


    /**
     * @throws \Exception
     */
    public function testOneReplicaOnOnlyOneTask(): void
    {
        $task = (new Task())->setJob($this->job)->setStatus(TaskStatus::READY);
        $this->infrastructure->getEntityManager()->persist($task);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->exec();

        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(1, $this->findValueOfKeyInHiera($result, 'replicas'));
    }


    /**
     * @throws \Exception
     */
    public function testReplicaIncreases(): void
    {
        $tasks = 1 + JobFactory::getDispatchConfigOfJob($this->job)->getDispatchConfig()->getTaskCountPerReplica();
        while ($tasks >= 0) {
            $task = (new Task())->setJob($this->job)->setStatus(TaskStatus::READY);
            $this->infrastructure->getEntityManager()->persist($task);
            $this->infrastructure->getEntityManager()->flush();
            $tasks--;
        }

        $result = $this->exec();
$body=(string)$result->getBody();
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(2, $this->findValueOfKeyInHiera($result, 'replicas'));
    }

    /**
     * @throws \Exception
     */
    public function testReplicaGetAppliedOnNewJob(): void
    {
        $this->runApp('POST', '/api/job/add?jobid=_NEW&token=' . $this->user->getToken(), true, null);
        $this->assertEquals('', ServerUtility::getLastExecutedShellCommand(), 'must not fire node init command as long as no job type is set');

        /** @var Job $precreatedJob */
        $precreatedJob = $this->jobRepository->findOneByName('precreated on request');

        $this->assertNotNull($precreatedJob);

        $this->runApp('POST', '/api/job/add?token=' . $this->user->getToken(), true, null, ['jobid' => $precreatedJob->getId(), 'jobtype' => JobType::ENERGY_PLUS_85, 'jobname' => 'testing 1551430480']);

        $this->assertContains('ssh', ServerUtility::getLastExecutedShellCommand());
        $this->assertContains('manager-' . ServerUtility::getShortHashOfString($precreatedJob->getId()) . '-0', ServerUtility::getLastExecutedShellCommand());
    }
}