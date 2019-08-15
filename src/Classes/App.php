<?php

namespace Helio\Panel;

use Ergy\Slim\Annotations\Router;
use Exception;
use Helio\Panel\Helper\SlackHelper;
use RuntimeException;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\ElasticHelper;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Helper\ZapierHelper;
use Helio\Panel\Utility\MiddlewareForHttpUtility;
use Helio\Panel\Utility\ServerUtility;
use Monolog\Logger;
use Slim\Views\PhpRenderer;

/**
 * Class App.
 *
 *
 * @OA\Info(title="Helio API", version="0.2.0")
 *
 *
 * @OA\Server(url="https://panel.idling.host/api", description="PROD API")
 * @OA\Server(url="https://panelprev.idling.host/api", description="STAGE API")
 * @OA\Server(url="https://panel.helio.test/api", description="DEV API")
 */
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

    /**
     * @var string
     */
    protected static $appName;

    /** @var DbHelper */
    protected static $dbHelperClassName = DbHelper::class;

    /** @var ZapierHelper */
    protected static $zapierHelperClassName = ZapierHelper::class;

    /** @var LogHelper */
    protected static $logHelperClassName = LogHelper::class;

    /** @var ElasticHelper */
    protected static $elasticHelperClassName = ElasticHelper::class;

    /** @var SlackHelper */
    protected static $slackHelperClassName = SlackHelper::class;

    /**
     * @param string|null $appName
     * @param array       $middleWaresToApply
     *
     * @return App
     *
     * @throws Exception
     */
    public static function getApp(
        ?string $appName = null,
        array $middleWaresToApply = [MiddlewareForHttpUtility::class]
    ): App {
        if (!self::$instance) {
            // abort if $instance should exist, but doesn't (e.g. if we call getApp from inside the application)
            if (null === $appName) {
                throw new RuntimeException('App instance cannot be created from here.', 1548056859);
            }

            // this is a kind of DI-hack to make the app testable
            self::$className = static::class;
            self::$appName = $appName;

            self::$instance = new self::$className(['settings' => [
                'displayErrorDetails' => !ServerUtility::isProd(),
            ]]);

            self::$instance->getContainer()['renderer'] = new PhpRenderer(APPLICATION_ROOT . '/src/templates');

            self::$instance->getContainer()['router'] = new Router(
                self::$instance,
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
     * @param string|null $appName
     * @param array       $middleWaresToApply
     *
     * @return App
     *
     * @throws Exception
     */
    public static function getNewApp(
        ?string $appName = null,
        array $middleWaresToApply = [MiddlewareForHttpUtility::class]
    ): App {
        self::$instance = null;
        self::$className = '';

        return self::getApp($appName, $middleWaresToApply);
    }

    /**
     * @return bool
     */
    public static function isReady(): bool
    {
        return (bool) self::$instance;
    }

    /**
     * @return DbHelper
     *
     * @throws Exception
     */
    public static function getDbHelper(): DbHelper
    {
        $class = self::$className;

        return ($class::$dbHelperClassName)::getInstance();
    }

    /**
     * @return Logger
     *
     * @throws Exception
     */
    public static function getLogger(): Logger
    {
        $class = self::$className;

        return ($class::$logHelperClassName)::getInstance(self::$appName);
    }

    /**
     * @return ZapierHelper
     *
     * @throws Exception
     */
    public static function getZapierHelper(): ZapierHelper
    {
        $class = self::$className;

        return ($class::$zapierHelperClassName)::getInstance();
    }

    /**
     * @return ElasticHelper
     *
     * @throws Exception
     */
    public static function getElasticHelper(): ElasticHelper
    {
        $class = self::$className;

        return ($class::$elasticHelperClassName)::getInstance();
    }

    /**
     * @return SlackHelper
     *
     * @throws Exception
     */
    public static function getSlackHelper(): SlackHelper
    {
        $class = self::$className;

        return ($class::$slackHelperClassName)::getInstance();
    }
}
