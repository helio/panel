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
     * @var array
     */
    protected $headers = [];


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
        $this->user->addJob($this->job);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->flush();


        $this->headers['Authorization'] = 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token'];
        $this->url .= '?jobid=' . $this->job->getId();

    }

    /**
     * @return ResponseInterface
     * @throws \Exception
     */
    protected function exec(): ResponseInterface
    {
        return $this->runApp('GET', $this->url, true, $this->headers);
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

        $execution = (new Execution())->setJob($this->job)->setStatus(ExecutionStatus::UNKNOWN);
        $this->infrastructure->getEntityManager()->persist($execution);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->exec();

        $this->assertEquals(200, $result->getStatusCode());
        $replicas = $this->findValueOfKeyInHiera($result, $this->job->getType() . '-' . $this->job->getId() . '-' . $execution->getId() . '.replicas');
        $this->assertEquals(0, $replicas);
    }


    /**
     * @throws \Exception
     */
    public function testOneReplicaOnOnlyOneExecution(): void
    {
        $execution = (new Execution())->setJob($this->job)->setStatus(ExecutionStatus::READY);
        $this->infrastructure->getEntityManager()->persist($execution);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->exec();

        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(1, $this->findValueOfKeyInHiera($result, $this->job->getType() . '-' . $this->job->getId() . '-' . $execution->getId() . '.replicas'));
    }


    /**
     * @throws \Exception
     */
    public function testEnvVariablesInHiera(): void
    {
        $this->job->setType(JobType::VF_DOCKER);
        $execution = (new Execution())->setJob($this->job)->setStatus(ExecutionStatus::READY);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->persist($execution);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->exec();

        $this->assertEquals(200, $result->getStatusCode());

        $envPath = $this->job->getType() . '-' . $this->job->getId() . '-' . $execution->getId() . '.env';
        $this->assertEquals($this->job->getId(), $this->findEnvElementOfArrayInHiera($result, $envPath, 'HELIO_JOBID'));
        $this->assertEquals($execution->getId(), $this->findEnvElementOfArrayInHiera($result, $envPath, 'HELIO_EXECUTIONID'));
        $this->assertEquals($this->job->getOwner()->getId(), $this->findEnvElementOfArrayInHiera($result, $envPath, 'HELIO_USERID'));
    }


    /**
     * @throws \Exception
     */
    public function testArgInHiera(): void
    {
        $this->job->setType(JobType::BUSYBOX);
        $execution = (new Execution())->setJob($this->job)->setStatus(ExecutionStatus::READY);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->persist($execution);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->exec();

        $this->assertEquals(200, $result->getStatusCode());
        $this->assertStringContainsString('sleep', $this->findValueOfKeyInHiera($result, $this->job->getType() . '-' . $this->job->getId() . '-' . $execution->getId() . '.args'));
    }


    /**
     * @throws \Exception
     */
    public function testReplicaIncreases(): void
    {
        $executions = 1 + JobFactory::getDispatchConfigOfJob($this->job)->getDispatchConfig()->getExecutionPerReplica();
        while ($executions >= 0) {
            $execution = (new Execution())->setJob($this->job)->setStatus(ExecutionStatus::READY);
            $this->infrastructure->getEntityManager()->persist($execution);
            $this->infrastructure->getEntityManager()->flush();
            $executions--;
        }

        $result = $this->exec();

        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(2, $this->findValueOfKeyInHiera($result, $this->job->getType() . '-' . $this->job->getId() . '-' . $execution->getId() . '.replicas'));
    }

    /**
     * @throws \Exception
     */
    public function testReplicaGetAppliedOnNewJob(): void
    {
        $this->runApp('POST', '/api/job/add', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']]);
        $this->assertEquals('', ServerUtility::getLastExecutedShellCommand(), 'must not fire node init command as long as no job type is set');

        /** @var Job $precreatedJob */
        $precreatedJob = $this->jobRepository->findOneByName('___NEW');

        $this->assertNotNull($precreatedJob);

        $this->runApp('POST', '/api/job/add', true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], ['jobid' => $precreatedJob->getId(), 'jobtype' => JobType::ENERGY_PLUS_85, 'jobname' => 'testing 1551430480']);

        $this->assertStringContainsString('ssh', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('manager-init-' . ServerUtility::getShortHashOfString($precreatedJob->getId()), ServerUtility::getLastExecutedShellCommand());
    }
}