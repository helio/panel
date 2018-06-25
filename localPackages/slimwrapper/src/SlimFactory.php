<?php

namespace Helio\SlimWrapper;

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
     * @var string
     */
    private $name;


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
    public function getApp(string $name): Slim
    {
        if (!$this->app) {
            $this->name = $name;

            $logger = (new \Monolog\Logger('panel.' . $name))
                ->pushProcessor(new \Monolog\Processor\UidProcessor())
                ->pushHandler(new \Monolog\Handler\StreamHandler(LOG_DEST, LOG_LVL));
            $renderer = new \Slim\Views\PhpRenderer(APPLICATION_ROOT . '/src/templates');

            $this->app = (new Slim($logger, $renderer))->setup([
                [APPLICATION_ROOT . '/src/controller/'],
                APPLICATION_ROOT . '/tmp/cache/' . $name
            ]);
        } elseif ($name !== $this->name) {
            throw new \RuntimeException('requested app of two different names, cannot coexists.');
        }

        return $this->app;
    }
}