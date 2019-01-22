<?php

namespace Helio\Panel;

use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Helper\ZapierHelper;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Slim\Http\Request;

class App extends \Slim\App
{


    /**
     * @var App
     */
    protected static $instance;


    /**
     * @param null|string $appName
     * @param Request|null $request
     * @param array $middleWaresToApply
     * @param string $dbHelperClassName
     * @param string $zapierHelperClassName
     * @param string $logHelperClassName
     * @return App
     * @throws \Exception
     */
    public static function getApp(
        ?string $appName = null,
        Request $request = null,
        array $middleWaresToApply = [JwtUtility::class],
        string $dbHelperClassName = DbHelper::class,
        string $zapierHelperClassName = ZapierHelper::class,
        string $logHelperClassName = LogHelper::class
    ): App
    {
        if (!self::$instance) {
            // abort if $instance should exist, but doesn't (e.g. if we call getApp from inside the application)
            if ($appName === null) {
                throw new \RuntimeException('App instance cannot be created from here.', 1548056859);
            }

            self::$instance = new self(['settings' => [
                'displayErrorDetails' => !ServerUtility::isProd(),
            ]]);
            /**
             * @var DbHelper $dbHelperClassName
             * @var LogHelper $logHelperClassName
             * @var ZapierHelper $zapierHelperClassName
             */
            self::$instance->getContainer()['logger'] = $logHelperClassName::get();
            self::$instance->getContainer()['dbHelper'] = $dbHelperClassName::getInstance();
            self::$instance->getContainer()['zapierHelper'] = $zapierHelperClassName::getInstance();
            self::$instance->getContainer()['renderer'] = new \Slim\Views\PhpRenderer(APPLICATION_ROOT . '/src/templates');

            if ($request) {
                self::$instance->getContainer()['request'] = $request;
            }

            self::$instance->getContainer()['router'] = new \Ergy\Slim\Annotations\Router(self::$instance,
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
}