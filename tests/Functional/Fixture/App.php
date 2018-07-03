<?php

namespace Helio\Test;

use Helio\Test\Functional\Fixture\Helper\DbHelper;
use Helio\Test\Functional\Fixture\Helper\ZapierHelper;

class App extends \Helio\Panel\App
{


    /**
     * @var App
     */
    protected static $testInstance;


    /**
     *
     * @return App
     * @throws \Exception
     */
    public static function getTestApp(): App
    {
        if (!self::$testInstance) {
            /**
             * @var DbHelper $dbHelperClassName
             * @var ZapierHelper $zapierHelperClassName
             */
            self::$testInstance = new self([
                'settings' => [
                    'displayErrorDetails' => !(SITE_ENV === 'PROD'),
                ],
                'logger' => (new \Monolog\Logger('helio.panel.test'))
                    ->pushProcessor(new \Monolog\Processor\UidProcessor())
                    ->pushHandler(new \Monolog\Handler\StreamHandler(LOG_DEST, LOG_LVL)),
                'renderer' => new \Slim\Views\PhpRenderer(APPLICATION_ROOT . '/src/templates'),
                'dbHelper' => DbHelper::getInstance(),
                'zapierHelper' => ZapierHelper::getInstance()
            ]);

            // router initialisation depends in an instance of app, so it has to happen after new()
            self::$testInstance->getContainer()['router'] = new \Ergy\Slim\Annotations\Router(self::$testInstance,
                [APPLICATION_ROOT . '/src/Classes/Controller/'],
                APPLICATION_ROOT . '/tmp/cache/test'
            );

        }
        return self::$testInstance;
    }
}