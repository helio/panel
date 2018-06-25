<?php
namespace Helio\Panel\Helper;

use Helio\SlimWrapper\Slim;
use Helio\SlimWrapper\SlimFactory;

class SlimHelper {


    /**
     * @param string $name
     *
     * @return Slim
     * @throws \Exception
     */
    public static function get(string $name): Slim {
        return SlimFactory::getFactory()->getApp($name);
    }
}