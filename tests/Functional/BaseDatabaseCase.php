<?php

namespace Helio\Test\Functional;

use Doctrine\Common\Persistence\ObjectRepository;
use Helio\Panel\Model\Server;
use Helio\Test\TestCase;
use Helio\Panel\Model\User;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

/**
 * Class BaseDatabaseCase
 *
 * @package    Helio\Test\Functional
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class BaseDatabaseCase extends TestCase
{


    /**
     * @var ORMInfrastructure
     */
    protected $infrastructure;


    /**
     * @var ObjectRepository
     */
    protected $userRepository;


    /**
     * @var ObjectRepository
     */
    protected $serverRepository;


    /** @see \PHPUnit_Framework_TestCase::setUp() */
    protected function setUp()
    {
        $this->infrastructure = ORMInfrastructure::createWithDependenciesFor([User::class, Server::class]);
        $this->userRepository = $this->infrastructure->getRepository(User::class);
        $this->serverRepository = $this->infrastructure->getRepository(Server::class);
    }
}