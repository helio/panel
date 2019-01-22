<?php

namespace Helio\Test\Functional;

use Doctrine\DBAL\Types\Type;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Type\UTCDateTimeType;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\ServerUtility;
use Helio\Test\TestCase;

class DatabaseTest extends TestCase
{
    public function testInfrastructureUserAggregateRoot(): void
    {

        // import fixture
        $user = (new User())->setName('testuser')->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()));
        $server = (new Instance())->setName('testserver')->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()));
        $user->addInstance($server);

        $this->infrastructure->import($user);

        $entitiesLoadedFromDatabase = $this->userRepository->findAll();

        /** @var User $foundUser */
        $this->assertCount(1, $entitiesLoadedFromDatabase);
        $foundUser = $entitiesLoadedFromDatabase[0];

        /** @var Instance $foundServer */
        $this->assertCount(1, $foundUser->getInstances());
        $foundServer = $foundUser->getInstances()[0];

        $this->assertEquals($user->getId(), $foundUser->getId());
        $this->assertEquals($server->getId(), $foundServer->getId());

        $this->assertEquals($user->getName(), $foundUser->getName());
        $this->assertEquals($server->getName(), $foundServer->getName());
    }


    public function testInfrastructureServerAggregateRoot(): void
    {

        // import fixture
        $user = (new User())->setName('testuser')->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()));
        $server = (new Instance())->setName('testserver')->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()));
        $server->setOwner($user);

        $this->infrastructure->import($server);

        $entitiesLoadedFromDatabase = $this->instanceRepository->findAll();

        /** @var Instance $foundServer */
        $this->assertCount(1, $entitiesLoadedFromDatabase);
        $foundServer = $entitiesLoadedFromDatabase[0];

        /** @var User $foundUser */
        $this->assertInstanceOf(User::class, $foundServer->getOwner());
        $foundUser = $foundServer->getOwner();

        $this->assertEquals($user->getId(), $foundUser->getId());
        $this->assertEquals($server->getId(), $foundServer->getId());

        $this->assertEquals($user->getName(), $foundUser->getName());
        $this->assertEquals($server->getName(), $foundServer->getName());
    }

    public function testObject(): void
    {
        try {
            $this->assertInstanceOf(UTCDateTimeType::class, Type::getType('datetimetz'));
        } catch (\Exception $e) {
            $this->assertTrue(false);
        }
    }

    public function testHiddenFilterActive(): void
    {
        $instance = (new Instance())->setName('hidden')->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()))->setHidden(true);
        $this->infrastructure->import($instance);
        $this->infrastructure->getEntityManager()->getFilters()->enable('deleted');
        $foundServer = $this->instanceRepository->findAll();

        $this->assertEmpty($foundServer);
    }

    public function testHiddenFilterInactive(): void
    {
        $instance = (new Instance())->setName('hidden')->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()))->setHidden(true);
        $this->infrastructure->import($instance);
        $foundServer = $this->instanceRepository->findAll();

        $this->assertCount(1, $foundServer);
    }


    public function testTimestampIssues(): void
    {

        // import fixture
        $user = (new User())->setName('testuser')->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()));
        $server = (new Instance())->setName('testserver')->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()));
        $server->setOwner($user);

        $this->infrastructure->import($server);

        /** @var Instance $foundServer */
        $foundServer = $this->instanceRepository->findOneByName('testserver');

        $this->assertNotNull($foundServer);
        $this->assertEquals($server->getCreated()->getTimestamp(), $foundServer->getCreated()->getTimestamp());
    }
}
