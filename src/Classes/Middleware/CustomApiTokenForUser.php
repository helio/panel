<?php

namespace Helio\Panel\Middleware;

use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tuupola\Middleware\DoublePassTrait;

/**
 * Class that allows access to
 *
 * @package    Helio\Panel\Middleware
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class CustomApiTokenForUser implements MiddlewareInterface
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
        if (\array_key_exists('token', $cookies) && strpos($cookies['token'], ':') === 8) {
            /** @var User $user */
            $user = DbHelper::getInstance()->getRepository(User::class)->findOneByToken($request->getCookieParams()['token']);
            if ($user && JwtUtility::verifyUserIdentificationToken($user, $request->getCookieParams()['token']) && strpos($request->getUri()->getPath(), '/api') === 0) {
                $cookies['token'] = JwtUtility::generateToken($user->getId())['token'];
                $cookies['block_reauth'] = 'true';
            }
        }
        return $handler->handle($request->withCookieParams($cookies));
    }
}