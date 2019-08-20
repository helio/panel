<?php

namespace Helio\Panel\Middleware;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\User;
use Helio\Panel\Service\UserService;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Http\Request;
use Tuupola\Middleware\DoublePassTrait;

/**
 * Class ReAuthenticate.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 */
class CliAuthenticate implements MiddlewareInterface
{
    /*
     * use process method instead of __invoke
     */
    use DoublePassTrait;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct()
    {
        $dbHelper = App::getDbHelper();
        $userRepository = $dbHelper->getRepository(User::class);
        $em = $dbHelper->get();
        $zapierHelper = App::getZapierHelper();
        $logger = LogHelper::getInstance();
        $this->userService = new UserService($userRepository, $em, $zapierHelper, $logger);
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     *
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Request $request */
        if ($request->getAttribute('JWT_SECRET', '') && $request->getServerParam('JWT_SECRET') === ServerUtility::get('JWT_SECRET')) {
            /** @var Job $job */
            $job = App::getDbHelper()->getRepository(Job::class)->find((int) $request->getServerParam('CLI_JOBID'));
            $user = $this->userService->findById((int) $request->getServerParam('CLI_USERID'));
            App::getApp()->getContainer()['job'] = $job;
            App::getApp()->getContainer()['user'] = $user;
        }

        return $handler->handle($request);
    }
}
