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
use Helio\Panel\Utility\ArrayUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Yaml\Yaml;

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
    public function setUp(): void
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
        $hiera = Yaml::parse((string)$response->getBody());
        return ArrayUtility::getFirstByDotNotation([$hiera], ['profile::docker::clusters.' . $key]);
    }

    /**
     * @param ResponseInterface $response
     * @param string $key
     * @param string $name
     * @return mixed|string
     */
    protected function findEnvElementOfArrayInHiera(ResponseInterface $response, string $key, string $name)
    {
        $hiera = Yaml::parse((string)$response->getBody());
        foreach (ArrayUtility::getFirstByDotNotation([$hiera], ['profile::docker::clusters.' . $key], []) as $env) {
            if (strpos($env, $name) !== false) {
                $matches = [];
                preg_match("/$name=\s*([^'$]+)/", (string)$response->getBody(), $matches);
                return $matches[1];
            }
        }
        return '';
    }


    /**
     * @throws \Exception
     */
    public function testNoReplicasOnEmptyJob(): void
    {

        $task = (new Task())->setJob($this->job)->setStatus(TaskStatus::UNKNOWN);
        $this->infrastructure->getEntityManager()->persist($task);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->exec();

        $replicas = $this->findValueOfKeyInHiera($result, $this->job->getType() . '-' . $this->job->getId() . '-' . $task->getId() . '.replicas');
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
        $this->assertEquals(1, $this->findValueOfKeyInHiera($result, $this->job->getType() . '-' . $this->job->getId() . '-' . $task->getId() . '.replicas'));
    }


    /**
     * @throws \Exception
     */
    public function testEnvVariablesInHiera(): void
    {
        $this->job->setType(JobType::VF_DOCKER);
        $task = (new Task())->setJob($this->job)->setStatus(TaskStatus::READY);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->persist($task);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->exec();

        $this->assertEquals(200, $result->getStatusCode());

        $envPath = $this->job->getType() . '-' . $this->job->getId() . '-' . $task->getId() . '.env';
        $this->assertEquals($this->job->getId(), $this->findEnvElementOfArrayInHiera($result, $envPath, 'HELIO_JOBID'));
        $this->assertEquals($task->getId(), $this->findEnvElementOfArrayInHiera($result, $envPath, 'HELIO_TASKID'));
        $this->assertEquals($this->job->getOwner()->getId(), $this->findEnvElementOfArrayInHiera($result, $envPath, 'HELIO_USERID'));
    }


    /**
     * @throws \Exception
     */
    public function testArgInHiera(): void
    {
        $this->job->setType(JobType::BUSYBOX);
        $task = (new Task())->setJob($this->job)->setStatus(TaskStatus::READY);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->persist($task);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->exec();

        $debug = "" . $result->getBody();
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertStringContainsString('sleep', $this->findValueOfKeyInHiera($result, $this->job->getType() . '-' . $this->job->getId() . '-' . $task->getId() . '.args'));
    }


    /**
     * @throws \Exception
     */
    public function testReplicaIncreases(): void
    {
        $tasks = 1 + JobFactory::getDispatchConfigOfJob($this->job)->getDispatchConfig()->getTaskPerReplica();
        while ($tasks >= 0) {
            $task = (new Task())->setJob($this->job)->setStatus(TaskStatus::READY);
            $this->infrastructure->getEntityManager()->persist($task);
            $this->infrastructure->getEntityManager()->flush();
            $tasks--;
        }

        $result = $this->exec();

        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(2, $this->findValueOfKeyInHiera($result, $this->job->getType() . '-' . $this->job->getId() . '-' . $task->getId() . '.replicas'));
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

        $this->assertStringContainsString('ssh', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('manager-init-' . ServerUtility::getShortHashOfString($precreatedJob->getId()), ServerUtility::getLastExecutedShellCommand());
    }
}