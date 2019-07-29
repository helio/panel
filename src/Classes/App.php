<?php

namespace Helio\Panel;

use Ergy\Slim\Annotations\Router;
use \Exception;
use \RuntimeException;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\ElasticHelper;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Helper\ZapierHelper;
use Helio\Panel\Utility\MiddlewareUtility;
use Helio\Panel\Utility\ServerUtility;
use Monolog\Logger;
use Slim\Views\PhpRenderer;

class App extends \Slim\App
{


    /**
     * @var App
     */
    protected static $instance;

    /**
     * @var App
     */
    protected static $className;

    /** @var DbHelper */
    protected static $dbHelperClassName = DbHelper::class;

    /** @var ZapierHelper */
    protected static $zapierHelperClassName = ZapierHelper::class;

    /** @var LogHelper */
    protected static $logHelperClassName = LogHelper::class;

    /** @var ElasticHelper */
    protected static $elasticHelperClassName = ElasticHelper::class;


    /**
     * @param null|string $appName
     * @param array $middleWaresToApply
     *
     * @return App
     * @throws Exception
     */
    public static function getApp(
        ?string $appName = null,
        array $middleWaresToApply = [MiddlewareUtility::class]
    ): App
    {

        if (!self::$instance) {
            // this is a kind of DI-hack to make the app testable
            self::$className = static::class;

            // abort if $instance should exist, but doesn't (e.g. if we call getApp from inside the application)
            if ($appName === null) {
                throw new RuntimeException('App instance cannot be created from here.', 1548056859);
            }

            self::$instance = new self::$className(['settings' => [
                'displayErrorDetails' => !ServerUtility::isProd(),
            ]]);

            self::$instance->getContainer()['renderer'] = new PhpRenderer(APPLICATION_ROOT . '/src/templates');

            self::$instance->getContainer()['router'] = new Router(self::$instance,
                [APPLICATION_ROOT . '/src/Classes/Controller/'],
                APPLICATION_ROOT . '/tmp/cache/' . $appName
            );

            foreach ($middleWaresToApply as $middleware) {
                $middleware::addMiddleware(self::$instance);
            }
        }

        return self::$instance;
    }


    /**
     * @return bool
     */
    public static function isReady(): bool
    {
        return (bool)self::$instance;
    }

    /**
     * @return DbHelper
     * @throws Exception
     */
    public static function getDbHelper()
    {
        /** @var App $debu */
        $class = self::$className;
        return ($class::$dbHelperClassName)::getInstance();
    }

    /**
     * @return Logger
     * @throws Exception
     */
    public static function getLogger()
    {
        /** @var App $debu */
        $class = self::$className;
        return ($class::$logHelperClassName)::getInstance();
    }

    /**
     * @return ZapierHelper
     * @throws Exception
     */
    public static function getZapierHelper()
    {
        /** @var App $debu */
        $class = self::$className;
        return ($class::$zapierHelperClassName)::getInstance();
    }

    /**
     * @return ElasticHelper
     * @throws Exception
     */
    public static function getElasticHelper()
    {
        /** @var App $debu */
        $class = self::$className;
        return ($class::$elasticHelperClassName)::getInstance();
    }
}