<?php

namespace Helio\Test\Functional;

use Helio\Panel\Model\Server;
use Helio\Panel\Model\User;
use Helio\Test\TestCase;

class DatabaseTest extends TestCase
{
    public function testInfrastructureUserAggregateRoot(): void
    {

        // import fixture
        $user = (new User())->setName('testuser');
        $server = (new Server())->setName('testserver');
        $user->addServer($server);

        $this->infrastructure->import($user);

        $entitiesLoadedFromDatabase = $this->userRepository->findAll();

        /** @var User $foundUser */
        $this->assertCount(1, $entitiesLoadedFromDatabase);
        $foundUser = $entitiesLoadedFromDatabase[0];

        /** @var Server $foundServer */
        $this->assertCount(1, $foundUser->getServers());
        $foundServer = $foundUser->getServers()[0];

        $this->assertEquals($user->getId(), $foundUser->getId());
        $this->assertEquals($server->getId(), $foundServer->getId());

        $this->assertEquals($user->getName(), $foundUser->getName());
        $this->assertEquals($server->getName(), $foundServer->getName());
    }


    public function testInfrastructureServerAggregateRoot(): void
    {

        // import fixture
        $user = (new User())->setName('testuser');
        $server = (new Server())->setName('testserver');
        $server->setOwner($user);

        $this->infrastructure->import($server);

        $entitiesLoadedFromDatabase = $this->serverRepository->findAll();

        /** @var Server $foundServer */
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
}
