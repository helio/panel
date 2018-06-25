<?php

namespace Helio\Panel;

use Helio\Panel\Helper\SlimHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class App
{


    /**
     * @param string $appName
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    public static function run(string $appName): ResponseInterface
    {
        return SlimHelper::get($appName)->run();
    }


    /**
     * @param string $appName
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    public static function process(string $appName, ServerRequestInterface $request = null, ResponseInterface $response = null): ResponseInterface
    {
        return SlimHelper::get($appName)->process($request, $response);
    }
}