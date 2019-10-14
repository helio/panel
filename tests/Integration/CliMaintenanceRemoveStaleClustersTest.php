<?php

namespace Helio\Test\Integration;

use Helio\Panel\Command\MaintenanceRemoveStaleClusters;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Manager;
use Helio\Panel\Model\User;
use Helio\Panel\Orchestrator\ManagerStatus;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Helper\TestHelper;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class CliMaintenanceRemoveStaleClustersTest extends TestCase
{
    /**
     * @var User
     */
    protected $user;

    /**
     * @var Job
     */
    protected $job;

    /**
     * @var Execution
     */
    protected $execution;

    public function setUp(): void
    {
        parent::setUp();

        $aDayAgo = (new \DateTime('now', ServerUtility::getTimezoneObject()))->sub(new \DateInterval('P1DT1H'));
        $managerOfDeletingJob = (new Manager())->setFqdn('manager-blubb')->setIp('1.2.3.4:9')->setWorkerToken('INITMANAGERTOKEN_BLAH')->setStatus(ManagerStatus::READY)->setManagerToken('TOKEN');

        $this->user = (new User())->setEmail('email@test.cli')->setActive(true)->setCreated();
        $this->job = (new Job())->setOwner($this->user)->setType(JobType::BUSYBOX)->setManager($managerOfDeletingJob)->setCreated($aDayAgo)->setLatestAction($aDayAgo)->setStatus(JobStatus::READY);
        $this->execution = (new Execution())->setJob($this->job)->setCreated($aDayAgo)->setStatus(ExecutionStatus::READY);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->persist($this->execution);
        $this->infrastructure->getEntityManager()->flush();
    }

    public function testCommandIsProperlySetup()
    {
        $result = $this->runCliApp(MaintenanceRemoveStaleClusters::class);
        $this->assertEquals(0, $result->getStatusCode());
    }

    /**
     * @throws \Exception
     */
    public function testCommandRemovesJobManager()
    {
        $result = $this->runCliApp(MaintenanceRemoveStaleClusters::class);
        $this->assertEquals(0, $result->getStatusCode());
        $this->assertStringContainsString('infrastructure::gce::delete', ServerUtility::getLastExecutedShellCommand());

        /** @var Execution $jobFromDb */
        $jobFromDb = $this->infrastructure->getRepository(Job::class)->find($this->job->getId());
        $this->assertEquals(JobStatus::READY_PAUSING, $jobFromDb->getStatus());

        $response = $this->runWebApp('POST', TestHelper::getCallbackUrlFromExecutedShellCommand(), true, ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']], ['deleted' => true, 'nodes' => $this->job->getManager()->getFqdn()]);
        $this->assertEquals(StatusCode::HTTP_OK, $response->getStatusCode());

        /** @var Execution $jobFromDb */
        $jobFromDb = $this->infrastructure->getRepository(Job::class)->find($this->job->getId());
        $this->assertEquals(JobStatus::READY_PAUSED, $jobFromDb->getStatus());
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testCommandDoesNotRemoveClusterNewerlyRunThanGracePeriod()
    {
        $result = $this->runCliApp(MaintenanceRemoveStaleClusters::class, ['maxIdlePeriod' => 'P5D']);
        $this->assertEquals(0, $result->getStatusCode());
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand());
    }
}
