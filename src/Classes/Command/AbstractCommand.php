<?php

namespace Helio\Panel\Command;

use Helio\Panel\App;
use Helio\Panel\Utility\MiddlewareForCliUtility;
use Slim\Http\Environment;
use Slim\Http\Request;
use Symfony\Component\Console\Command\Command;

abstract class AbstractCommand extends Command
{
    /** @var App */
    protected $app;

    public function __construct(string $appClassName = App::class, $middlewaresToApply = [MiddlewareForCliUtility::class])
    {
        parent::__construct();
        $request = Request::createFromEnvironment(Environment::mock([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
        ]));

        /* @var App $appClassName */
        $this->app = $appClassName::getApp('cli', $middlewaresToApply);
        $this->app->getContainer()['request'] = $request;
    }
}
