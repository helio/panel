<?php

namespace Helio\Panel;

use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\ZapierHelper;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Monolog\Logger;

class App extends \Slim\App
{


    /**
     * @var App
     */
    protected static $instance;


    /**
     * @param string|null $appName if null, fail if not yet instanced
     * @param string $dbHelperClassName
     * @param string $zapierHelperClassName
     * @param string[] $middleWaresToApply
     *
     * @return App
     * @throws \Exception
     */
    public static function getApp(
        ?string $appName = 'app',
        string $dbHelperClassName = DbHelper::class,
        string $zapierHelperClassName = ZapierHelper::class,
        array $middleWaresToApply = [JwtUtility::class]
    ): App {
        if (!self::$instance) {
            // abort if $instance should exist, but doesn't (e.g. if we call getApp from inside the application)
            if ($appName === null) {
                throw new \RuntimeException('App instance cannot be created from here.');
            }

            /**
             * @var DbHelper $dbHelperClassName
             * @var ZapierHelper $zapierHelperClassName
             */
            self::$instance = new self([
                'settings' => [
                    'displayErrorDetails' => !(ServerUtility::get('SITE_ENV') === 'PROD'),
                ],
                'logger' => (new Logger('helio.panel.' . $appName))
                    ->pushProcessor(new \Monolog\Processor\UidProcessor())
                    ->pushHandler(new \Monolog\Handler\StreamHandler(LOG_DEST, LOG_LVL)),
                'renderer' => new \Slim\Views\PhpRenderer(APPLICATION_ROOT . '/src/templates'),
                'dbHelper' => $dbHelperClassName::getInstance(),
                'zapierHelper' => $zapierHelperClassName::getInstance()
            ]);
            Logger::setTimezone(ServerUtility::getTimezoneObject());

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