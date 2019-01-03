<?php

namespace Helio\Panel\Middleware;

use Helio\Panel\Helper\DbHelper;
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
            /**
             * @var DbHelper $dbHelper
             * @var User $user
             */
            $dbHelper = $this->container['dbHelper'];
            $user = $dbHelper->getRepository(User::class)->findOneById($this->container['jwt']['uid']);
            if ($user->getLoggedOut()) {
                $tokenGenerationTime = new \DateTime('now', ServerUtility::getTimezoneObject());
                $tokenGenerationTime->setTimestamp($this->container['jwt']['iat']);

                $userLoggedOutTime = $user->getLoggedOut()->setTimezone(ServerUtility::getTimezoneObject());

                if ($userLoggedOutTime > $tokenGenerationTime) {
                    throw new \RuntimeException('Token Expired.');
                }
            }

            // mark all tokens older than the current as invalid so they can't be used anymore.
            $user->setLoggedOut((new \DateTime('now', ServerUtility::getTimezoneObject()))->setTimestamp($this->container['jwt']['iat']));
            $dbHelper->merge($user);
            $dbHelper->flush();

            $this->container['user'] = $user;
        }

        return $handler->handle($request);

    }
}