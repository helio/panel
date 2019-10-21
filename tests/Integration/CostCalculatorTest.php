<?php

namespace Helio\Test\Integration;

use Exception;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Manager;
use Helio\Panel\Model\User;
use Helio\Panel\Orchestrator\ManagerStatus;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Utility\ServerUtility;
use Helio\Test\TestCase;
use Slim\Http\StatusCode;

class CostCalculatorTest extends TestCase
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
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->user = (new User())
            ->setAdmin(1)
            ->setName('testuser')
            ->setEmail('test-autoscaler@example.com'
            )->setActive(true)->setCreated();

        $this->infrastructure->getEntityManager()->persist($this->user);
        $this->infrastructure->getEntityManager()->flush();
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testEstimatesAreBasicallyCorrect(): void
    {
        $manager = Manager::createManager()
            ->setStatus(ManagerStatus::READY);
        $job = (new Job())
            ->setManager($manager)
            ->setType(JobType::BUSYBOX)
            ->setOwner($this->user)
            ->setStatus(JobStatus::READY)
            ->setBudget(10);

        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush();

        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']];
        $response = $this->runWebApp('POST', '/api/job/' . $job->getId() . '/execute', true, $tokenHeader);
        $result = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('estimates', $result, print_r($result, true));

        $this->assertEquals(1030, $result['estimates']['duration']);

        $completedTimestamp = $result['estimates']['completion'];
        $now = (new \DateTime('now', ServerUtility::getTimezoneObject()))->getTimestamp();
        $this->assertEqualsWithDelta($now + 1030, $completedTimestamp, 1.0);

        $cost = $result['estimates']['cost'];
        $this->assertGreaterThan(0.0, $cost);
        $this->assertLessThan(0.1, $cost);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testEstimatesAreCorrectWithCustomLimit(): void
    {
        $manager = Manager::createManager()
            ->setStatus(ManagerStatus::READY);
        $job = (new Job())
            ->setManager($manager)
            ->setType(JobType::BUSYBOX)
            ->setOwner($this->user)
            ->setStatus(JobStatus::READY)
            ->setBudget(10);

        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush();
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']];
        $result = json_decode($this->runWebApp('POST', '/api/job/' . $job->getId() . '/execute', true, $tokenHeader, ['limit' => 10])->getBody(), true);
        $this->assertArrayHasKey('estimates', $result);

        $this->assertEquals(130, $result['estimates']['duration']);

        $completedTimestamp = $result['estimates']['completion'];
        $now = (new \DateTime('now', ServerUtility::getTimezoneObject()))->getTimestamp();
        $this->assertEqualsWithDelta($now + 130, $completedTimestamp, 1.0);

        $cost = $result['estimates']['cost'];
        $this->assertGreaterThan(0.0, $cost);
        $this->assertLessThan(0.01, $cost);
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testBudgetGetsUsedUp(): void
    {
        $this->markTestSkipped('Currently, we don\'t block users from execute, because the budget handling is far from complete.');
        $job = (new Job())->setType(JobType::BUSYBOX)->setOwner($this->user)->setStatus(JobStatus::READY)->setBudget(1);
        $this->infrastructure->getEntityManager()->persist($job);
        $this->infrastructure->getEntityManager()->flush();
        $tokenHeader = ['Authorization' => 'Bearer ' . JwtUtility::generateToken(null, $this->user)['token']];

        $result = $this->runWebApp('POST', '/api/job/' . $job->getId() . '/execute', true, $tokenHeader);
        $this->assertEquals(200, $result->getStatusCode());
        $result = $this->runWebApp('POST', '/api/job/' . $job->getId() . '/execute', true, $tokenHeader, ['limit' => 10000]);
        $this->assertEquals(StatusCode::HTTP_FORBIDDEN, $result->getStatusCode());
    }
}
