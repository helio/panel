<?php

namespace Helio\Panel\Middleware;

use \Exception;
use Helio\Panel\App;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\CookieUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
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
class CliAuthenticate implements MiddlewareInterface
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
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Request $request */
        if ($request->getAttribute('JWT_SECRET', '') && $request->getServerParam('JWT_SECRET') === ServerUtility::get('JWT_SECRET')) {
            /** @var Job $job */
            $job = App::getDbHelper()->getRepository(Job::class)->find((int)$request->getServerParam('CLI_JOBID'));
            $user = App::getDbHelper()->getRepository(User::class)->find((int)$request->getServerParam('CLI_USERID'));
            App::getApp()->getContainer()['job'] = $job;
            App::getApp()->getContainer()['user'] = $user;
        }
$debug=App::getApp()->getContainer();
        return $handler->handle($request);
    }
}