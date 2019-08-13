<?php

namespace Helio\Test\Infrastructure\Helper;

use GuzzleHttp\Handler\MockHandler;

class ZapierHelper extends \Helio\Panel\Helper\ZapierHelper
{
    /**
     * @var array
     */
    protected static $responseStack;

    /**
     * @return mixed
     */
    protected function getHandler(): MockHandler
    {
        return new MockHandler(self::$responseStack);
    }

    /**
     * @return mixed
     */
    protected function hasHandler()
    {
        return true;
    }

    public static function setResponseStack(array $stack): void
    {
        self::$responseStack = $stack;
    }

    public static function reset(): void
    {
        self::$instances = null;
    }
}
