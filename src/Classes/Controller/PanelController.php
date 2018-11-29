<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Model\Server;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\CookieUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PanelController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/panel')
 *
 */
class PanelController extends AbstractController
{


    /**
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     *
     * @Route("", methods={"GET", "POST"}, name="panel.index")
     */
    public function indexAction(): ResponseInterface
    {
        $changed = false;
        $message = '';

        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);

        $params = $this->request->getParsedBody();
        if ($params['username']) {
            $user->setName(filter_var($params['username'], FILTER_SANITIZE_STRING));
            $changed = true;
        }
        if ($params['role']) {
            $user->setRole(filter_var($params['role'], FILTER_SANITIZE_STRING));
            $changed = true;
        }


        if ($user && !$user->isActive()) {
            $user->setActive(true);
            $message = 'user successfully activated';
            $changed = true;
        }

        if ($changed) {
            $this->dbHelper->merge($user);
            $this->dbHelper->flush();
            $message .= ($message ? ' and ' : '') . 'changes stored to database.';
        }

        return $this->render('panel/index', [
            'user' => $user,
            'message' => $message,
            'title' => 'Helio Panel',
            'selectedServer' => 0,
            'panelMode' => ''
        ]);

    }

    /**
     * @return ResponseInterface
     *
     * @Route("/buy", "methods={"GET", "POST"}, name="panel.buy")
     */
    public function BuyAction(): ResponseInterface
    {
        $message = '';
        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);

        $params = $this->request->getParsedBody();
        return $this->render('panel/index', [
            'user' => $user,
            'message' => $message,
            'title' => 'Helio Panel',
            'panelMode' => 'buy',
            'buyActive' => 'active',
            'module' => 'dashboard',
            'buyDashboardActive' => 'active'
        ]);

    }

    /**
     * @return ResponseInterface
     *
     * @Route("/sell", methods={"GET", "POST"}, name="panel.sell")
     */
    public function SellAction(): ResponseInterface
    {
        $message = '';
        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);

        return $this->render('panel/index', [
            'user' => $user,
            'message' => $message,
            'title' => 'Helio Panel',
            'panelMode' => 'sell',
            'sellActive' => 'active',
            'module' => 'dashboard',
            'sellDashboardActive' => 'active'
        ]);

    }


    /**
     * @return ResponseInterface
     *
     * @Route("/sell/dashboard", methods={"GET", "POST"}, name="panel.sell.dashboard")
     */
    public function SellDashboardAction(): ResponseInterface
    {

        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);
        $server = $this->request->getAttribute('server') ?? 0;
        $message = '';

        return $this->render('panel/index', [
            'user' => $user,
            'message' => $message,
            'title' => 'Server Dashboar - Helio Panel',
            'selectedServer' => (int)$server,
            'panelMode' => 'sell',
            'sellActive' => 'active',
            'dashboardActive' => 'active',
            'module' => 'dashboard'
        ]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/server/add", methods={"GET", "POST"}, name="panel.server.log")
     */
    public function ServerAddAction(): ResponseInterface
    {

        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);
        $server = $this->request->getParam('server') ?? 0;
        $message = '';
        $params = $this->request->getParsedBody();

        if ($params['servername']) {
            $servername = filter_var($params['servername'], FILTER_SANITIZE_STRING);

            $server = new Server();
            $server->setName($servername);
            $server->setCreated(new \DateTime('now', ServerUtility::$timeZone));
            $server->setOwner($user);

            // flush server because we need the generated ID
            $this->dbHelper->persist($server);
            $this->dbHelper->flush($server);
            $server->setToken(JwtUtility::generateServerIdentificationToken($server));

            $changed = true;
            $message = 'server added';
        }
        return $this->render('panel/index', [
            'user' => $user,
            'message' => $message,
            'title' => 'Server Log - Helio Panel',
            'selectedServer' => (int)$server,
            'panelMode' => 'sell',
            'sellActive' => 'active',
            'module' => 'add',
            'addSrvActive' => 'active',
            'srvActive' => 'active'
        ]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/server/status", methods={"GET", "POST"}, name="panel.server.log")
     */
    public function ServerStatusAction(): ResponseInterface
    {

        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);
        $server = $this->request->getParam('server') ?? 0;
        $message = '';
        $params = $this->request->getParsedBody();

        if ($params['stopserver']) {
            $serverToStop = filter_var($params['stopserver'], FILTER_SANITIZE_STRING);
            /** @var Server $server */
            $server = $this->dbHelper->getRepository(Server::class)->findOneById($serverToStop);
            if ($server && ServerUtility::submitStopRequest($server)) {
                $server->setRunning(false);
                $changed = true;
                $message = 'server stopped';
            }
        }
        if ($params['startserver']) {
            $serverToStart = filter_var($params['stopserver'], FILTER_SANITIZE_STRING);
            /** @var Server $server */
            $server = $this->dbHelper->getRepository(Server::class)->findOneById($serverToStart);
            if ($server && ServerUtility::submitStopRequest($server)) {
                $server->setRunning(true);
                $changed = true;
                $message = 'server started';
            }
        }

        return $this->render('panel/index', [
            'user' => $user,
            'message' => $message,
            'title' => 'Server Log - Helio Panel',
            'selectedServer' => (int)$server,
            'panelMode' => 'sell',
            'sellActive' => 'active',
            'module' => 'status',
            'statusActive' => 'active',
            'srvActive' => 'active'
        ]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/server/logs", methods={"GET", "POST"}, name="panel.server.log")
     */
    public function ServerLogAction(): ResponseInterface
    {

        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);
        $server = $this->request->getParam('server') ?? 0;
        $message = '';

        return $this->render('panel/index', [
            'user' => $user,
            'message' => $message,
            'title' => 'Server Log - Helio Panel',
            'selectedServer' => (int)$server,
            'panelMode' => 'sell',
            'sellActive' => 'active',
            'module' => 'logs',
            'logsActive' => 'active',
            'srvActive' => 'active'
        ]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/server/metrics", methods={"GET", "POST"}, name="panel.server.log")
     */
    public function ServerMetricsAction(): ResponseInterface
    {

        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);
        $server = $this->request->getParam('server') ?? 0;
        $message = '';

        return $this->render('panel/index', [
            'user' => $user,
            'message' => $message,
            'title' => 'Server Log - Helio Panel',
            'selectedServer' => (int)$server,
            'panelMode' => 'sell',
            'sellActive' => 'active',
            'module' => 'metrics',
            'metricsActive' => 'active',
            'srvActive' => 'active'
        ]);
    }


    /**
     * Note: This has to be here becuase in the "user" module, we don't have the jwt information since that section isn't protected.
     *
     * @return ResponseInterface
     *
     * @Route("/logout", methods={"GET"}, name="user.logout")
     */
    public function LogoutUserAction(): ResponseInterface
    {
        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);
        $user->setLoggedOut();
        $this->dbHelper->persist($user);
        $this->dbHelper->flush($user);

        return CookieUtility::deleteCookie($this->render('user/loggedout',
            [
                'success' => true,
                'title' => 'Logout Successful.'
            ]
        ), 'token');
    }
}