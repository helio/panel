<?php

namespace Helio\SlimWrapper;

use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Views\PhpRenderer;

class Slim
{


    /**
     * @var \Slim\App
     */
    protected $app;


    /**
     * slim constructor.
     *
     * @param Logger $logger
     * @param PhpRenderer $renderer
     */
    public function __construct(Logger $logger, PhpRenderer $renderer)
    {

        $logger->addDebug('starting init');
        // Instantiate the app
        $this->app = new App([
                'settings' => [
                    'displayErrorDetails' => !(SITE_ENV === 'PROD'),
                ],
                'logger' => $logger,
                'renderer' => $renderer
            ]
        );
    }


    /**
     * @param array $routerConfig
     * @return $this
     * @throws \Exception
     */
    public function setup(array $routerConfig): self
    {
        [$controller, $cache] = $routerConfig;
        $this->app->getContainer()['router'] = new \Ergy\Slim\Annotations\Router($this->app, $controller, $cache);

        $this->app->get('/', function () {
            echo 'success';
        });

        return $this;
    }


    /**
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Slim\Exception\MethodNotAllowedException
     * @throws \Slim\Exception\NotFoundException
     */
    public function run(): ResponseInterface
    {
        return $this->app->run();
    }


    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     * @throws \Slim\Exception\MethodNotAllowedException
     * @throws \Slim\Exception\NotFoundException
     */
    public function process(ServerRequestInterface $request = null, ResponseInterface $response = null): ResponseInterface
    {
        if ($request) {
            $this->app->getContainer()['request'] = $request;
        }
        if ($response) {
            $this->app->getContainer()['response'] = $response;
        }

        return $this->app->run(true);
    }
}