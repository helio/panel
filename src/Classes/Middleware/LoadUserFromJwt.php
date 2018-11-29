<?php

namespace Helio\Panel\Middleware;

use Helio\Panel\Model\User;
use Helio\Panel\Utility\ServerUtility;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tuupola\Middleware\DoublePassTrait;

/**
 * Class ReAuthenticate
 *
 * @package    Helio\Panel\Middleware
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class LoadUserFromJwt implements MiddlewareInterface
{


    protected $container;


    /**
     * LoadUserFromJwt constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


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
        if (isset($this->container['jwt']['uid'])) {
            /** @var User $user */
            $user = $this->container['dbHelper']->getRepository(User::class)->findOneById($this->container['jwt']['uid']);
            if ($user->getLoggedOut()) {
                $tokenGenerationTime = new \DateTime('now', new \DateTimeZone(ServerUtility::$timeZone));
                $tokenGenerationTime->setTimestamp($this->container['jwt']['iat']);

                $userLoggedOutTime = $user->getLoggedOut()->setTimezone(new \DateTimeZone(ServerUtility::$timeZone));

                if ($userLoggedOutTime >= $tokenGenerationTime) {
                    throw new \RuntimeException('Token Expired.');
                }
            }
            $this->container['user'] = $user;
        }

        return $handler->handle($request);

    }
}