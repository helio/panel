<?php

namespace Helio\DbWrapper;

use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

class Db
{


    /**
     * @var array<EntityManager>
     */
    protected $entityManager = [];


    /**
     * @var bool
     */
    protected $devMode;


    /**
     * @var array
     */
    protected $dbCfg;


    /**
     * db constructor.
     */
    public function __construct()
    {
        $this->devMode = !(SITE_ENV === 'PROD');

        // database configuration parameters
        $this->dbCfg = array (
            'driver' => 'pdo_mysql',
            'dbname' => $_SERVER['DB_NAME'],
            'user' => $_SERVER['DB_USERNAME'],
            'password' => $_SERVER['DB_PASSWORD'],
            'host' => $_SERVER['DB_HOST'] ?: 'localhost',
            'port' => array_key_exists('DB_PORT', $_SERVER) ? $_SERVER['DB_PORT'] : 3306
        );
    }


    /**
     *
     * @param string $pathToModels path where the models are stored
     * @return EntityManager
     *
     * @throws \InvalidArgumentException
     * @throws ORMException
     */
    public function get(string $pathToModels): EntityManager
    {
        if (!$pathToModels || !is_dir($pathToModels)) {
            throw new \InvalidArgumentException('invalid path submitted to db->get()');
        }

        // normalize path so it is suitable for identifying the cache entry
        $pathToModels = realpath($pathToModels);

        if (!array_key_exists($pathToModels, $this->entityManager)) {
            $this->entityManager[$pathToModels] = EntityManager::create($this->dbCfg,
                Setup::createAnnotationMetadataConfiguration(array ($pathToModels), $this->devMode));
        }

        return $this->entityManager[$pathToModels];
    }
}