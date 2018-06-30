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
    private $app;


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

  protected $hasMiddleware;


    /**
     * @param bool $addMiddleware
     * @param string $name
     *
     * @return Slim
     * @throws \Exception
     */
    public function getApp(bool $addMiddleware = true, string $name = 'app'): Slim
    {
        if (!$this->app) {

            $logger = (new \Monolog\Logger('panel.' . $name))
                ->pushProcessor(new \Monolog\Processor\UidProcessor())
                ->pushHandler(new \Monolog\Handler\StreamHandler(LOG_DEST, LOG_LVL));

            $renderer = new \Slim\Views\PhpRenderer(APPLICATION_ROOT . '/src/templates');

            $this->app = (new Slim($logger, $renderer))->setup([
                [APPLICATION_ROOT . '/src/controller/'],
                APPLICATION_ROOT . '/tmp/cache/' . $name
            ]);

            $this->hasMiddleware = $addMiddleware;

            if ($addMiddleware) {
                JwtHelper::addMiddleware($this->app);
            }
        }

        if ($addMiddleware !== $this->hasMiddleware) {
            throw new \RuntimeException('You cannot dynamically add/remove middleware', 1530458392);
        }

        return $this->app;
    }
}