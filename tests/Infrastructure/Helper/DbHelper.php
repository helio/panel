<?php

namespace Helio\Test\Infrastructure\Helper;

use Doctrine\ORM\EntityManager;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

class DbHelper extends \Helio\Panel\Helper\DbHelper
{
    /** @var ORMInfrastructure $infrastructure */
    protected static $infrastructure;

    /**
     * @param $infrastructure
     */
    public static function setInfrastructure($infrastructure): void
    {
        self::$infrastructure = $infrastructure;
    }

    public function getConnection(): EntityManager
    {
        return self::$infrastructure->getEntityManager();
    }

    public function getRepository(string $entityName)
    {
        return self::$infrastructure->getRepository($entityName);
    }

    /**
     * @return array
     */
    protected function getConnectionSettings(): array
    {
        return [
            'driver' => self::$infrastructure->getEntityManager()->getConnection()->getDriver()->getName(),
            'user' => self::$infrastructure->getEntityManager()->getConnection()->getUsername(),
            'password' => self::$infrastructure->getEntityManager()->getConnection()->getPassword(),
            'memory' => true,
        ];
    }

    public static function reset(): void
    {
        self::$instances = null;
    }
}
