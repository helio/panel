<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Model\Server;
use Helio\Panel\Model\User;
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
        if ($params['servername']) {
            $servername = filter_var($params['servername'], FILTER_SANITIZE_STRING);

            $server = new Server();
            $server->setName($servername);
            $server->setCreated(new \DateTime('now'));
            $server->setOwner($user);

            // flush server because we need the generated ID
            $this->dbHelper->persist($server);
            $this->dbHelper->flush($server);
            $server->setToken(JwtUtility::generateServerIdentificationToken($server));

            $changed = true;
            $message = 'server added';
        }
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


        if ($user && !$user->isActive()) {
            $user->setActive(true);
            $message = 'user successfully activated';
            $changed = true;
        }

        if ($changed) {
            $this->dbHelper->merge($user);
            $this->dbHelper->flush();
        }

        return $this->render('panel/index', [
            'user' => $user,
            'message' => $message,
            'title' => 'Helio Panel'
        ]);
    }
}