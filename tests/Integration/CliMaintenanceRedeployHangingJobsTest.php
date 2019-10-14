<?php

namespace Helio\Test\Integration;

use Helio\Panel\Command\MaintenanceRedeployHangingJobs;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Manager;
use Helio\Panel\Model\User;
use Helio\Panel\Orchestrator\ManagerStatus;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;

class CliMaintenanceRedeployHangingJobsTest extends TestCase
{
    /**
     * @var User
     */
    protected $user;

    /**
     * @var Job
     */
    protected $jobInInitState;

    /**
     * @var Job
     */
    protected $jobInDeletingState;

    public function setUp(): void
    {
        parent::setUp();

        $anHourAgo = (new \DateTime('now', ServerUtility::getTimezoneObject()))->sub(new \DateInterval('PT1H'));
        $managerOfDeletingJob = (new Manager())->setFqdn('manager-blubb')->setIp('1.2.3.4:9')->setWorkerToken('INITMANAGERTOKEN_BLAH')->setStatus(ManagerStatus::READY)->setManagerToken('TOKEN');

        $this->user = (new User())->setEmail('email@test.cli')->setActive(true)->setCreated();
        $this->jobInInitState = (new Job())->setOwner($this->user)->setType(JobType::BUSYBOX)->setCreated()->setLatestAction($anHourAgo)->setStatus(JobStatus::INIT);
        $this->jobInDeletingState = (new Job())->setManager($managerOfDeletingJob)->setOwner($this->user)->setType(JobType::BUSYBOX)->setCreated()->setLatestAction($anHourAgo)->setStatus(JobStatus::DELETING);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->persist($managerOfDeletingJob);
        $this->infrastructure->getEntityManager()->persist($this->jobInInitState);
        $this->infrastructure->getEntityManager()->persist($this->jobInDeletingState);
        $this->infrastructure->getEntityManager()->flush();
    }

    public function testCommandIsProperlySetup()
    {
        $result = $this->runCliApp(MaintenanceRedeployHangingJobs::class);
        $this->assertEquals(0, $result->getStatusCode());
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testCommandReschedulesInit()
    {
        $this->jobInInitState->setStatus(JobStatus::INIT_ERROR);
        $this->infrastructure->getEntityManager()->persist($this->jobInInitState);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->runCliApp(MaintenanceRedeployHangingJobs::class);
        $this->assertEquals(0, $result->getStatusCode());
        $this->assertStringContainsString('infrastructure::gce::create', ServerUtility::getLastExecutedShellCommand());
        $this->assertStringContainsString('manager-', ServerUtility::getLastExecutedShellCommand());

        /** @var Job $jobInInitStateFromDb */
        $jobInInitStateFromDb = $this->infrastructure->getRepository(Job::class)->find($this->jobInInitState->getId());
        $this->assertEquals(JobStatus::INIT, $jobInInitStateFromDb->getStatus());
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testCommandReschedulesDelete()
    {
        $this->jobInDeletingState->setStatus(JobStatus::DELETING_ERROR);
        $this->infrastructure->getEntityManager()->persist($this->jobInDeletingState);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->runCliApp(MaintenanceRedeployHangingJobs::class);
        $this->assertEquals(0, $result->getStatusCode());
        $debug = ServerUtility::getLastExecutedShellCommand();
        $debug1 = ServerUtility::getLastExecutedShellCommand(1);
        $debug2 = ServerUtility::getLastExecutedShellCommand(2);
        $this->assertStringContainsString('helio::task::update', ServerUtility::getLastExecutedShellCommand(1));
        $this->assertStringContainsString('infrastructure::gce::delete', ServerUtility::getLastExecutedShellCommand());
        /** @var Job $jobInDeletingStateFromDb */
        $jobInDeletingStateFromDb = $this->infrastructure->getRepository(Job::class)->find($this->jobInDeletingState->getId());
        $this->assertEquals(JobStatus::DELETING, $jobInDeletingStateFromDb->getStatus());
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testCommandDoesNotRescheduleOnToBigGracePeriod()
    {
        $this->jobInInitState->setStatus(JobStatus::INIT_ERROR);
        $this->infrastructure->getEntityManager()->persist($this->jobInInitState);
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->runCliApp(MaintenanceRedeployHangingJobs::class, ['gracePeriod' => 'PT3H']);
        $this->assertEquals(0, $result->getStatusCode());
        $this->assertEmpty(ServerUtility::getLastExecutedShellCommand());

        /** @var Job $jobInInitStateFromDb */
        $jobInInitStateFromDb = $this->infrastructure->getRepository(Job::class)->find($this->jobInInitState->getId());
        $this->assertEquals(JobStatus::INIT_ERROR, $jobInInitStateFromDb->getStatus());
    }
}
