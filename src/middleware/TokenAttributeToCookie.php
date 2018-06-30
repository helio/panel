<?php

namespace Helio\Panel\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tuupola\Middleware\DoublePassTrait;

/**
 * Class AttributeToCookie
 *
 * @package    Helio\Panel\Middleware
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class TokenAttributeToCookie implements MiddlewareInterface
{


    /**
     * use process method instead of __invoke
     */
    use DoublePassTrait;


    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     * @throws \Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $cookies = $request->getCookieParams();
        if ($request->getAttribute('token') && $request->getAttribute('user')) {
            $cookies['token'] = $request->getAttribute('token');
        } elseif (array_key_exists('user', $_REQUEST) && array_key_exists('token', $_REQUEST)) {
            $cookies['token'] = $_REQUEST['token'];
        }
        $request = $request->withCookieParams($cookies);

        return $handler->handle($request);
    }
}