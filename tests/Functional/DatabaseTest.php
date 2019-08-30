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

        /* @var User $foundUser */
        $this->assertCount(1, $entitiesLoadedFromDatabase);
        $foundUser = $entitiesLoadedFromDatabase[0];

        /* @var Instance $foundServer */
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

        /* @var Instance $foundServer */
        $this->assertCount(1, $entitiesLoadedFromDatabase);
        $foundServer = $entitiesLoadedFromDatabase[0];

        /* @var User $foundUser */
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
        $this->assertEqualsWithDelta($server->getCreated()->getTimestamp(), $foundServer->getCreated()->getTimestamp(), 1.0);
        $this->assertEqualsWithDelta($server->getCreated()->getTimezone()->getName(), $foundServer->getCreated()->getTimezone()->getName(), 1.0);
    }

    public function testTimestampAutoSetter(): void
    {
        // import fixture
        $user = (new User())->setName('testuser')->setCreated();
        $server = (new Instance())->setName('testserver')->setCreated();
        $server->setOwner($user);

        $this->infrastructure->import($server);

        /** @var Instance $foundServer */
        $foundServer = $this->instanceRepository->findOneByName('testserver');

        $this->assertNotNull($foundServer);
        $this->assertEqualsWithDelta($server->getCreated()->getTimestamp(), $foundServer->getCreated()->getTimestamp(), 1.0);
        $this->assertEqualsWithDelta($server->getCreated()->getTimezone()->getName(), $foundServer->getCreated()->getTimezone()->getName(), 1.0);
    }

    public function testBitFlagConversionDefaultValues(): void
    {
        $user = (new User())->setCreated()->setName('testuer');
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush();

        /** @var User $foundUser */
        $foundUser = $this->infrastructure->getRepository(User::class)->find($user->getId());
        $this->assertTrue($foundUser->getPreferences()->getNotifications()->isEmailOnJobReady());
    }

    public function testBitFlagConversionManipulation(): void
    {
        /** @var User $user */
        $user = (new User())->setCreated()->setName('testuer');
        $this->assertFalse($user->getPreferences()->getNotifications()->isMuteAdmin());
        $user->getPreferences()->getNotifications()->setMuteAdmin(true);
        $this->infrastructure->getEntityManager()->persist($user);
        $this->infrastructure->getEntityManager()->flush();

        /** @var User $foundUser */
        $foundUser = $this->infrastructure->getRepository(User::class)->find($user->getId());
        $foundUser->getPreferences()->getNotifications()->isMuteAdmin();
    }
}
