<?php

namespace Helio\Panel\Helper;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Setup;
use Helio\Panel\Model\Filter\DeletedFilter;
use Helio\Panel\Model\QueryFunction\TimestampDiff;
use Helio\Panel\Model\Type\UTCDateTimeType;
use Helio\Panel\Utility\ServerUtility;

/**
 * Class DbHelper.
 *
 * @method ObjectRepository|EntityRepository getRepository(string $entityName)
 * @method                                   persist($entity)
 * @method                                   merge($entity)
 * @method                                   remove($entity)
 * @method                                   flush($entity = null)
 *
 * @author    Christoph Buchli <support@snowflake.ch>
 */
class DbHelper implements HelperInterface
{
    /**
     * @var array<DbHelper>
     */
    protected static $instances;

    /** @var EntityManager */
    protected $db;

    /**
     * @return $this
     */
    public static function getInstance(): self
    {
        $class = static::class;
        if (!self::$instances || !\array_key_exists($class, self::$instances)) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    /**
     * @return EntityManager
     *
     * @throws \Exception
     *
     * @deprecated should be replaced with proxy methods, only kept here for cli-config.php
     */
    public function get(): EntityManager
    {
        return $this->getConnection();
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->getConnection(), $name)) {
            return \call_user_func_array([$this->getConnection(), $name], $arguments);
        }
        throw new \InvalidArgumentException("Method $name not a method of the EntityManager", 1530901357);
    }

    /**
     * @return EntityManager
     *
     * @throws \Exception
     */
    protected function getConnection(): EntityManager
    {
        if (!$this->db) {
            if (!$this->getPathToModels() || !is_dir($this->getPathToModels())) {
                throw new \InvalidArgumentException('invalid path submitted to DbFactory->getConnection()', 1530565724);
            }

            if (!$this->getConnectionSettings()) {
                throw new \InvalidArgumentException('invalid DB connection settings', 1548043213);
            }

            Type::overrideType('datetime', UTCDateTimeType::class);
            Type::overrideType('datetimetz', UTCDateTimeType::class);

            $configObject = Setup::createAnnotationMetadataConfiguration([realpath($this->getPathToModels())], !ServerUtility::isProd(), ServerUtility::getTmpPath());
            $configObject->setAutoGenerateProxyClasses(!ServerUtility::isProd());
            $configObject->addCustomNumericFunction('timestampdiff', TimestampDiff::class);

            // add filters
            foreach ($this->getFilters() as $name => $filter) {
                $configObject->addFilter($name, $filter);
            }

            $this->db = EntityManager::create($this->getConnectionSettings(), $configObject);

            // enable filters
            foreach ($this->getFilters() as $name => $filter) {
                $this->db->getFilters()->enable($name);
            }
        }

        return $this->db;
    }

    /**
     * @return array
     */
    protected function getConnectionSettings(): array
    {
        return [
            'driver' => 'pdo_mysql',
            'dbname' => ServerUtility::get('DB_NAME'),
            'user' => ServerUtility::get('DB_USERNAME'),
            'password' => ServerUtility::get('DB_PASSWORD'),
            'host' => ServerUtility::get('DB_HOST', 'localhost'),
            'port' => ServerUtility::get('DB_PORT', 3306),
        ];
    }

    /**
     * @return mixed
     */
    protected function getPathToModels()
    {
        return APPLICATION_ROOT . '/src/Classes/Model';
    }

    /**
     * @return array
     */
    protected function getFilters(): array
    {
        return [
            'deleted' => DeletedFilter::class,
        ];
    }
}
