<?php

namespace Helio\Panel;

use Helio\SlimWrapper\SlimFactory;
use Psr\Http\Message\ResponseInterface;

class App
{


    /**
     * @return ResponseInterface
     * @throws \Exception
     */
    public static function run(): ResponseInterface
    {
        return SlimFactory::getFactory()->getApp()->run();
    }
}