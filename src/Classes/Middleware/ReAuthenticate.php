<?php

namespace Helio\Panel\Middleware;

use Helio\Panel\Utility\CookieUtility;
use Helio\Panel\Utility\JwtUtility;
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
 * @author    Christoph Buchli <team@opencomputing.cloud>
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

        if ($token && strpos($request->getUri()->getPath(), '/api') === false && $request->getUri()->getPath() !== '/panel/logout') {
            $token = JwtUtility::generateNewTokenForCurrentSession('+120 minutes');
            # re-authenticate by setting a new token
            $response = CookieUtility::addCookie($response, 'token', $token['token'], $token['expires']);
        }

        return $response;
    }
}