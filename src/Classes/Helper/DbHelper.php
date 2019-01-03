<?php

namespace Helio\Panel\Helper;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Setup;
use Helio\Panel\Filter\DeletedFilter;
use Helio\Panel\Utility\ServerUtility;

/**
 * Class DbHelper
 *
 * @method ObjectRepository|EntityRepository getRepository(string $entityName)
 * @method persist($entity)
 * @method merge($entity)
 * @method remove($entity)
 * @method flush($entity = null)
 *
 * @package    Helio\Panel\Helper
 * @author    Christoph Buchli <support@snowflake.ch>
 */
class DbHelper
{


    /**
     * @var DbHelper
     */
    protected static $helper;


    /** @var EntityManager */
    protected $db;


    /**
     *
     * @return DbHelper
     */
    public static function getInstance(): DbHelper
    {
        if (!self::$helper) {
            self::$helper = new self();
        }

        return self::$helper;
    }


    /**
     *
     * @return EntityManager
     * @throws \Doctrine\ORM\ORMException
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
     * @throws \Doctrine\ORM\ORMException
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->getConnection(), $name)) {
            return \call_user_func_array([$this->getConnection(), $name], $arguments);
        }
        throw new \InvalidArgumentException("Method $name not a method of the EntityManager", 1530901357);
    }

    /**
     *
     * @return EntityManager
     * @throws \Doctrine\ORM\ORMException
     */
    protected function getConnection(): EntityManager
    {
        if (!$this->db) {
            if (!$this->getPathToModels() || !is_dir($this->getPathToModels())) {
                throw new \InvalidArgumentException('invalid path submitted to DbFactory->getConnection()', 1530565724);
            }

            // database configuration parameters
            $dbCfg = array(
                'driver' => 'pdo_mysql',
                'dbname' => ServerUtility::get('DB_NAME'),
                'user' => ServerUtility::get('DB_USERNAME'),
                'password' => ServerUtility::get('DB_PASSWORD'),
                'host' => ServerUtility::get('DB_HOST', 'localhost'),
                'port' => ServerUtility::get('DB_PORT', 3306)
            );


            // normalize path so it is suitable for identifying the cache entry
            $pathToModels = realpath($this->getPathToModels());

            $configObject = Setup::createAnnotationMetadataConfiguration([$pathToModels], true);
            foreach ($this->getFilters() as $name => $filter) {
                $configObject->addFilter($name, $filter);
            }

            $this->db = EntityManager::create($dbCfg, $configObject);

            foreach ($this->getFilters() as $name => $filter) {
                $this->db->getFilters()->enable($name);
            }
        }

        return $this->db;
    }


    /**
     *
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
            'deleted' => DeletedFilter::class
        ];
    }
}
