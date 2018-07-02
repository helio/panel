<?php

namespace Helio\Panel\Helper;

use Firebase\JWT\JWT;
use Helio\Panel\App;
use Helio\Panel\Middleware\TokenAttributeToCookie;
use Helio\Panel\Middleware\ReAuthenticate;
use Helio\Panel\Model\User;
use Helio\SlimWrapper\Slim;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tuupola\Base62;

use Slim\Http\Request;
use Slim\Http\Response;
use Tuupola\Middleware\CallableHandler;
use Tuupola\Middleware\CorsMiddleware;
use Tuupola\Middleware\JwtAuthentication;
use Tuupola\Middleware\JwtAuthentication\RequestMethodRule;
use Tuupola\Middleware\JwtAuthentication\RequestPathRule;

class JwtHelper
{


    /**
     * @param Slim $app
     *
     * NOTE: Middlewares are processed as a FILO stack, so beware their order
     */
    public static function addMiddleware(Slim $app): void
    {

        $container = $app->getApp()->getContainer();

        $container['jwt'] = function () {
            return [];
        };

        $app->add(new ReAuthenticate());

        $app->add(new JwtAuthentication([
            'logger' => $container['logger'],
            'secret' => self::getSecret(),
            'rules' => [
                new RequestPathRule([
                    'path' => '/panel'
                ]),
                new RequestMethodRule(['passthrough' => ['OPTIONS']]),
            ],
            'before' => function (Request $request, array $arguments) use ($container) {
                $container['jwt'] = $arguments['decoded'];
            },
            'error' => function (Response $response, array $arguments) {
                $data['status'] = 'error';
                $data['message'] = $arguments['message'];

                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            }
        ]));

        $app->add(new TokenAttributeToCookie());

        $app->add(new CorsMiddleware([
            'logger' => $container['logger'],
            'origin' => ['*'],
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
            'headers.allow' => ['Authorization', 'If-Match', 'If-Unmodified-Since'],
            'headers.expose' => ['Authorization', 'Etag'],
            'credentials' => true,
            'cache' => 60,
            'error' => function (Request $request, Response $response) {
                return $response->withJson(['status' => 'error'], 401);
            }
        ]));

    }


    /**
     * @param string $userIdHash
     * @param string $duration
     *
     * @return array
     * @throws \Exception
     */
    public static function generateToken(string $userIdHash, string $duration = '+10 minutes'): array
    {

        $now = new \DateTime();
        $future = new \DateTime($duration);
        $jti = (new Base62())->encode(random_bytes(16));
        $payload = [
            'iat' => $now->getTimestamp(),
            'exp' => $future->getTimestamp(),
            'jti' => $jti,
            'uid' => $userIdHash
        ];
        $secret = self::getSecret();
        $token = JWT::encode($payload, $secret, 'HS256');

        return [
            'token' => $token,
            'expires' => $future->getTimestamp()
        ];
    }


    /**
     *
     * @return string
     * @throws \RuntimeException
     */
    public static function getSecret(): string
    {

        if (!array_key_exists('JWT_SECRET', $_SERVER) || !$_SERVER['JWT_SECRET']) {
            throw new \RuntimeException('please set the ENV Variable JWT_SECRET', 1530357047);
        }

        return $_SERVER['JWT_SECRET'];
    }
}