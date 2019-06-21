<?php

namespace Helio\Test\Infrastructure;

use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Utility\JwtUtility;
use Helio\Test\Infrastructure\Helper\DbHelper;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\Infrastructure\Helper\ElasticHelper;
use Slim\Http\Request;

class App extends \Helio\Panel\App
{
    /**
     * @var array
     */
    protected static $instances;


    /**
     * @var
     */
    protected static $currentIndex;

    /**
     * @inheritdoc
     */
    public static function getApp(
        ?string $appName = null,
        Request $request = null,
        array $middleWaresToApply = [JwtUtility::class],
        string $dbHelperClassName = DbHelper::class,
        string $zapierHelperClassName = ZapierHelper::class,
        string $logHelperClassName = LogHelper::class,
        string $elasticHelperClassName = ElasticHelper::class
    ): \Helio\Panel\App
    {
        // if a new test is run, we increase the instance index to ensure no two tests run on the same app instance.
        if ($appName === 'test') {
            ++self::$currentIndex;
        }


        if (!self::$instances || !\array_key_exists('test-' . self::$currentIndex, self::$instances)) {
            self::$instance = null;
            self::$instances['test-' . self::$currentIndex] = \Helio\Panel\App::getApp('test-' . self::$currentIndex, $request, $middleWaresToApply, DbHelper::class, ZapierHelper::class, LogHelper::class);
        }
        return self::$instances['test-' . self::$currentIndex];
    }
}