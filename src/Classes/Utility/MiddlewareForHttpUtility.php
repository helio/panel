<?php

namespace Helio\Panel\Utility;

use Exception;
use DateTime;
use Helio\Panel\App;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Middleware\ReAuthenticate;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;
use Helio\Panel\Service\UserService;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use Tuupola\Middleware\CorsMiddleware;
use Tuupola\Middleware\JwtAuthentication;
use Tuupola\Middleware\JwtAuthentication\RequestMethodRule;
use Tuupola\Middleware\JwtAuthentication\RequestPathRule;

/**
 * Class MiddlewareUtility.
 */
class MiddlewareForHttpUtility extends AbstractUtility
{
    /**
     * @param App $app
     *
     * NOTE: Middlewares are processed as a FILO stack, so beware their order
     *
     * @throws Exception
     */
    public static function addMiddleware(App $app): void
    {
        $dbHelper = App::getDbHelper();
        $userRepository = $dbHelper->getRepository(User::class);
        $em = $dbHelper->get();
        $zapierHelper = App::getZapierHelper();
        $logger = LogHelper::getInstance();
        $userService = new UserService($userRepository, $em, $zapierHelper, $logger);

        $app->add(new ReAuthenticate());

        $app->add(new JwtAuthentication([
            'logger' => LogHelper::getInstance('jwt'),
            'secret' => ServerUtility::get('JWT_SECRET'),
            'rules' => [
                new RequestPathRule([
                    'path' => '/(api|panel)',
                    'ignore' => '/api/login',
                ]),
                new RequestMethodRule(['passthrough' => ['OPTIONS']]),
            ],
            'before' => function (Request $request, array $arguments) use ($userService) {
                // set user if authenticated via jwt
                if (array_key_exists('u', $arguments['decoded'])) {
                    /** @var User $user */
                    $user = $userService->findById($arguments['decoded']['u']);
                    if ($user->getLoggedOut() && !array_key_exists('sticky', $arguments['decoded'])) {
                        $tokenGenerationTime = (new DateTime('now', ServerUtility::getTimezoneObject()))->setTimestamp($arguments['decoded']['iat']);
                        $userLoggedOutTime = $user->getLoggedOut()->setTimezone(ServerUtility::getTimezoneObject());

                        if ($userLoggedOutTime <= $tokenGenerationTime) {
                            App::getApp()->getContainer()['user'] = $user;
                        }
                    }

                    // impersonation feature for admin users completely mocks another user
                    if (array_key_exists('impersonate', $request->getCookieParams()) && $user->isAdmin() && (string) (int) $request->getCookieParams()['impersonate'] === (string) $request->getCookieParams()['impersonate']) {
                        App::getApp()->getContainer()['impersonatinguser'] = clone $user;
                        $user = $userService->findById((int) $request->getCookieParams()['impersonate']) ?? $user;
                    }

                    App::getApp()->getContainer()['user'] = $user;
                }

                // set instance if authenticated via jwt
                if (array_key_exists('i', $arguments['decoded'])) {
                    /** @var Instance $instance */
                    $instance = App::getDbHelper()->getRepository(Instance::class)->find($arguments['decoded']['i']);
                    App::getApp()->getContainer()['instance'] = $instance;
                    if (!App::getApp()->getContainer()->has('user')) {
                        App::getApp()->getContainer()['user'] = $instance->getOwner();
                    }
                }

                // set job if authenticated via jwt
                if (array_key_exists('j', $arguments['decoded'])) {
                    /** @var Job $job */
                    $job = App::getDbHelper()->getRepository(Job::class)->find($arguments['decoded']['j']);
                    App::getApp()->getContainer()['job'] = $job;
                    if (!App::getApp()->getContainer()->has('user')) {
                        App::getApp()->getContainer()['user'] = $job->getOwner();
                    }
                }
            },
            'error' => function (Response $response, array $arguments) {
                $data['status'] = 'error';
                $data['message'] = $arguments['message'];

                /** @var RequestInterface $request */
                $request = App::getApp()->getContainer()['request'];
                if (0 === strpos($request->getUri()->getPath(), '/api')) {
                    return CookieUtility::deleteCookie($response
                        ->withHeader('Content-Type', 'application/json')
                        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)), 'token');
                }

                return CookieUtility::deleteCookie($response
                    ->withHeader('Location', '/')
                    ->withStatus(StatusCode::HTTP_SEE_OTHER), 'token');
            },
        ]));

        $app->add(new CorsMiddleware([
            'logger' => LogHelper::getInstance('cors'),
            'origin' => ['*'],
            'headers.allow' => ['Authorization', 'If-Match', 'If-Unmodified-Since', 'Content-Type', 'X-Upload-Content-Type', 'X-Upload-Content-Length', 'Content-Range'],
            'headers.expose' => ['Authorization', 'Etag'],
            'credentials' => true,
            'cache' => 60,
            'error' => function (Request $request, Response $response) {
                if ('application/json' === mb_strtolower($request->getContentType())) {
                    return $response->withJson(['status' => 'cors error'], StatusCode::HTTP_UNAUTHORIZED);
                }

                return $response->write('<html lang="en"><head><title>Error</title></head><body><p><strong>Status:</strong>cors  error</p></body>')->withStatus(StatusCode::HTTP_UNAUTHORIZED);
            },
        ]));

        if (ServerUtility::isLocalDevEnv()) {
            $app->add(new \RKA\Middleware\ProxyDetection([$_SERVER['REMOTE_ADDR']]));
        }

        $app->add(function (ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface {
            $requestId = $request->getHeader('X-Choria-Request-ID');
            if (!$requestId) {
                $requestId = Uuid::uuid4()->toString();
            }
            App::getApp()->getContainer()['requestId'] = $requestId;

            LogHelper::pushProcessorToAllInstances(function (array $record) use ($requestId): array {
                $record['extra']['requestId'] = $requestId;

                return $record;
            });

            return $next($request, $response);
        });
    }
}
