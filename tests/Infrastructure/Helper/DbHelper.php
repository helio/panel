<?php

namespace Helio\Test\Infrastructure\Helper;

use Doctrine\ORM\EntityManager;
use Webfactory\Doctrine\ORMTestInfrastructure\ORMInfrastructure;

class DbHelper extends \Helio\Panel\Helper\DbHelper
{
    /** @var ORMInfrastructure $infarastructure */
    protected static $infarastructure;

    /**
     * @param $infrastructure
     */
    public static function setInfrastructure($infrastructure): void
    {
        self::$infarastructure = $infrastructure;
    }

    public function getConnection(): EntityManager
    {
        return self::$infarastructure->getEntityManager();
    }

    public function getRepository(string $entityName)
    {
        return self::$infarastructure->getRepository($entityName);
    }

    /**
     * @return array
     */
    protected function getConnectionSettings(): array
    {
        return [
            'driver' => self::$infarastructure->getEntityManager()->getConnection()->getDriver()->getName(),
            'user' => self::$infarastructure->getEntityManager()->getConnection()->getUsername(),
            'password' => self::$infarastructure->getEntityManager()->getConnection()->getPassword(),
            'memory' => true,
        ];
    }

    public static function reset(): void
    {
        self::$instances = null;
    }
}
