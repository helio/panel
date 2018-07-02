<?php

namespace Helio\Panel\Middleware;

use Helio\Panel\Helper\CookieHelper;
use Helio\Panel\Helper\JwtHelper;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Http\Request;
use Tuupola\Middleware\DoublePassTrait;

/**
 * Class ReAuthenticate
 *
 * @package    Helio\Panel\Middleware
 * @author    Christoph Buchli <support@snowflake.ch>
 */
class ReAuthenticate implements MiddlewareInterface
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
        # first, call all the other middlewares to ensure that the user is properly authenticated
        $response = $handler->handle($request);

        /** @var Request $request */
        $token = $request->getAttribute('token');

        if ($token) {
            $token = JwtHelper::generateToken($token['uid']);
            # re-authenticate by setting a new token
            $response = CookieHelper::addCookie($response, 'token', $token['token'], $token['expires']);
        }

        return $response;
    }
}