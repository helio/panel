<?php

namespace Helio\Panel\Middleware;

use Helio\Panel\Model\User;
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
        if (isset($this->container['jwt'])) {
            $this->container['user'] = $this->container['dbHelper']->getRepository(User::class)->findOneById($this->container['jwt']['uid']);
        }

        return $handler->handle($request);

    }
}