<?php

namespace Helio\SlimWrapper;

use Helio\Panel\Helper\JwtHelper;

class SlimFactory
{


    /**
     * @var SlimFactory
     */
    private static $factory;


    /**
     * @var Slim
     */
    protected $app;


    /**
     * @var Slim
     */
    protected $appWithoutMiddleware;


    protected $hasMiddleware;


    /**
     *
     * @return SlimFactory
     */
    public static function getFactory(): SlimFactory
    {
        if (!self::$factory) {
            self::$factory = new self();
        }

        return self::$factory;
    }


    /**
     * @param string $name
     *
     * @return Slim
     * @throws \Exception
     */
    public function getApp(string $name = 'app'): Slim
    {
        if (!$this->app) {
            $this->app = $this->getAppWithoutMiddleware($name);
                JwtHelper::addMiddleware($this->app);

        }

        return $this->app;
    }


    /**
     * @param string $name
     *
     * @return Slim
     * @throws \Exception
     */
    public function getAppWithoutMiddleware(string $name = 'app'): Slim
    {
        if (!$this->appWithoutMiddleware) {

            $logger = (new \Monolog\Logger('panel.' . $name))
                ->pushProcessor(new \Monolog\Processor\UidProcessor())
                ->pushHandler(new \Monolog\Handler\StreamHandler(LOG_DEST, LOG_LVL));

            $renderer = new \Slim\Views\PhpRenderer(APPLICATION_ROOT . '/src/templates');

            $this->appWithoutMiddleware = (new Slim($logger, $renderer))->setup([
                [APPLICATION_ROOT . '/src/controller/'],
                APPLICATION_ROOT . '/tmp/cache/' . $name
            ]);
        }
        return $this->appWithoutMiddleware;
    }
}