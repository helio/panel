<?php

namespace Helio\Panel;

use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\ZapierHelper;
use Helio\Panel\Utility\JwtUtility;

class App extends \Slim\App
{


    /**
     * @var App
     */
    protected static $instance;


    /**
     * @param string $appName
     * @param string $dbHelperClassName
     * @param string $zapierHelperClassName
     * @param string[] $middleWaresToApply
     *
     * @return App
     * @throws \Exception
     */
    public static function getApp(
        string $appName = 'app',
        string $dbHelperClassName = DbHelper::class,
        string $zapierHelperClassName = ZapierHelper::class,
        array $middleWaresToApply = [JwtUtility::class]
    ): App {
        if (!self::$instance) {
            /**
             * @var DbHelper $dbHelperClassName
             * @var ZapierHelper $zapierHelperClassName
             */
            self::$instance = new self([
                'settings' => [
                    'displayErrorDetails' => !(SITE_ENV === 'PROD'),
                ],
                'logger' => (new \Monolog\Logger('helio.panel.' . $appName))
                    ->pushProcessor(new \Monolog\Processor\UidProcessor())
                    ->pushHandler(new \Monolog\Handler\StreamHandler(LOG_DEST, LOG_LVL)),
                'renderer' => new \Slim\Views\PhpRenderer(APPLICATION_ROOT . '/src/templates'),
                'dbHelper' => $dbHelperClassName::getInstance(),
                'zapierHelper' => $zapierHelperClassName::getInstance()
            ]);

            // router initialisation depends in an instance of app, so it has to happen after new()
            self::$instance->getContainer()['router'] = new \Ergy\Slim\Annotations\Router(self::$instance,
                [APPLICATION_ROOT . '/src/Classes/Controller/'],
                APPLICATION_ROOT . '/tmp/cache/' . $appName
            );
            // middleware initialisation depends in an instance of app, so it has to happen after new()
            foreach ($middleWaresToApply as $middleware) {
                $middleware::addMiddleware(self::$instance);
            }
        }

        return self::$instance;
    }


}