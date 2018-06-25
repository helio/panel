<?php

namespace Helio\DbWrapper;

class DbFactory
{


    /**
     * @var DbFactory
     */
    private static $factory;


    /**
     * @var Db
     */
    private $db;


    /**
     *
     * @return DbFactory
     */
    public static function getFactory(): DbFactory
    {
        if (!self::$factory) {
            self::$factory = new self();
        }

        return self::$factory;
    }


    /**
     *
     * @return Db
     */
    public function getConnection(): Db
    {
        if (!$this->db) {
            $this->db = new db();
        }

        return $this->db;
    }
}