<?php

namespace Helio\Test\Integration;

use Helio\Panel\Command\ExecuteScheduledJob;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;
use Helio\Test\TestCase;

class CliExecuteScheduledJobTest extends TestCase
{
    /**
     * @var User
     */
    protected $user;

    /**
     * @var Job
     */
    protected $job;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = (new User())->setEmail('email@test.cli')->setActive(true)->setCreated();
        $this->job = (new Job())->setOwner($this->user)->setType(JobType::BUSYBOX)->setCreated()->setStatus(JobStatus::READY);
        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->persist($this->job);
        $this->infrastructure->getEntityManager()->flush();
    }

    public function testCommandIsProperlySetup()
    {
        $result = $this->runCliApp(ExecuteScheduledJob::class);
        $this->assertEquals(0, $result->getStatusCode());
    }

    public function testCommandDisplaysHelp()
    {
        $executions = $this->infrastructure->getRepository(Execution::class)->findBy(['job' => $this->job]);
        $this->assertCount(0, $executions);

        $this->job->setAutoExecSchedule('* * * * *');
        $this->infrastructure->getEntityManager()->flush();

        $result = $this->runCliApp(ExecuteScheduledJob::class);
        $this->assertEquals(0, $result->getStatusCode());

        $executions = $this->infrastructure->getRepository(Execution::class)->findBy(['job' => $this->job]);
        $this->assertCount(1, $executions);
    }
}
