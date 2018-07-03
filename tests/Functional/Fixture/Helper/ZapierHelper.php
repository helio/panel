<?php

namespace Helio\Test\Functional\Fixture\Helper;


use GuzzleHttp\Handler\MockHandler;

class ZapierHelper extends \Helio\Panel\Helper\ZapierHelper
{


    private static $testHelper;


    /**
     * @var array
     */
    protected $responseStack;


    public static function getTestInstance(): ZapierHelper
    {
        if (!self::$testHelper) {
            self::$testHelper = new self();
        }

        return self::$testHelper;
    }


    /**
     *
     * @return mixed
     */
    protected function getHandler(): MockHandler
    {
        return new MockHandler($this->responseStack);
    }


    /**
     * @return mixed
     */
    protected function hasHandler()
    {
        return true;
    }


    public function setResponseStack(array $stack): ZapierHelper
    {
        $this->responseStack = $stack;

        return $this;
    }
}