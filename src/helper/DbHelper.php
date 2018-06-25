<?php
namespace Helio\Panel\Helper;

use Doctrine\ORM\EntityManager;
use Helio\DbWrapper\DbFactory;

class DbHelper {


    /**
     *
     * @return \Doctrine\ORM\EntityManager
     * @throws \Doctrine\ORM\ORMException
     */
    public static function get(): EntityManager {
        return DbFactory::getFactory()->getConnection()->get(APPLICATION_ROOT . '/src/model');
    }
}