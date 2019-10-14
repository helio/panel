<?php

namespace Helio\Test\Integration;

use Helio\Panel\Command\MaintenanceRerunHangingExecution;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Manager;
use Helio\Panel\Model\User;
use Helio\Panel\Orchestrator\ManagerStatus;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;

class CliMaintenanceRerunHangingExecutionTest extends TestCase
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

        $aDayAgo = (new \DateTime('now', ServerUtility::getTimezoneObject()))->sub(new \DateInterval('P1D'));

        $this->user = (new User())
            ->setEmail('email@test.cli')
            ->setActive(true)
            ->setCreated();
        $this->job = (new Job())
            ->setOwner($this->user)
            ->setType(JobType::BUSYBOX)
            ->setManager((new Manager())
                ->setStatus(ManagerStatus::READY)
                ->setManagerToken('TOKEN')
                ->setFqdn('manager-blubb')
                ->setIp('1.2.3.4')
                ->setWorkerToken('INITMANAGERTOKEN_BLAH')
            )
            ->setCreated($aDayAgo)
            ->setLatestAction($aDayAgo)
            ->setStatus(JobStatus::READY);
        $this->execution = (new Execution())
            ->setJob($this->job)
            ->setCreated($aDayAgo)
            ->setStatus(ExecutionStatus::READY);

        $em = $this->infrastructure->getEntityManager();
        $em->persist($this->user);
        $em->persist($this->job);
        $em->persist($this->execution);
        $em->flush();
    }

    public function testCommandIsProperlySetup()
    {
        $result = $this->runCliApp(MaintenanceRerunHangingExecution::class);
        $this->assertEquals(0, $result->getStatusCode());
    }

    public function testCommandRerunsJob()
    {
        $result = $this->runCliApp(MaintenanceRerunHangingExecution::class);
        $this->assertEquals(0, $result->getStatusCode());
        $this->assertStringContainsString('helio::queue', ServerUtility::getLastExecutedShellCommand());

        /** @var Execution $executionFromDb */
        $executionFromDb = $this->infrastructure->getRepository(Execution::class)->find($this->execution->getId());
        $this->assertEquals(ExecutionStatus::READY, $executionFromDb->getStatus());
    }

    public function testCommandDoesNotRerunJobOnOlderGracePeriod()
    {
        $result = $this->runCliApp(MaintenanceRerunHangingExecution::class, ['gracePeriod' => 'P5D']);
        $this->assertEquals(0, $result->getStatusCode());
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand());
    }
}
