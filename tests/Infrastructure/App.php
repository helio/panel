<?php

namespace Helio\Test\Infrastructure;

use Doctrine\Common\Annotations\AnnotationReader;
use \Exception;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\MiddlewareForHttpUtility;
use Helio\Test\Infrastructure\Helper\DbHelper;
use Helio\Test\Infrastructure\Helper\LogHelper;
use Helio\Test\Infrastructure\Helper\ZapierHelper;
use Helio\Test\Infrastructure\Helper\ElasticHelper;

class App extends \Helio\Panel\App
{
    /**
     * @var array
     */
    protected static $instances;


    /**
     * @var int
     */
    protected static $currentIndex;

    /** @var DbHelper */
    protected static $dbHelperClassName = DbHelper::class;

    /** @var ZapierHelper */
    protected static $zapierHelperClassName = ZapierHelper::class;

    /** @var LogHelper */
    protected static $logHelperClassName = LogHelper::class;

    /** @var ElasticHelper */
    protected static $elasticHelperClassName = ElasticHelper::class;


    /**
     * @param bool $cleanInstance
     * @param array $middleWaresToApply
     * @return \Helio\Panel\App
     * @throws Exception
     */
    public static function getTestApp(
        bool $cleanInstance = false,
        array $middleWaresToApply = [MiddlewareForHttpUtility::class]
    ): \Helio\Panel\App
    {
        if ($cleanInstance) {
            // if a new test is run, we increase the instance index to ensure no two tests run on the same app instance.
            ++self::$currentIndex;
        }


        if (!self::$instances || !\array_key_exists('test-' . self::$currentIndex, self::$instances)) {
            self::$instance = null;
            self::$instances['test-' . self::$currentIndex] = static::getApp('test-' . self::$currentIndex, $middleWaresToApply);
        }
        return self::$instances['test-' . self::$currentIndex];
    }
}