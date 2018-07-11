<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Model\Server;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
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
     * @throws \Exception
     *
     * @Route("/addserver", methods={"POST"}, name="panel.index")
     */
    public function addServerAction(): ResponseInterface
    {
        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);

        $servername = filter_var($this->request->getParsedBodyParam('servername'), FILTER_SANITIZE_STRING);

        $server = new Server();
        $server->setName($servername);
        $server->setCreated(new \DateTime('now'));
        $server->setOwner($user);
        $this->dbHelper->persist($server);
        $this->dbHelper->flush($server);

        $server->setToken(JwtUtility::generateServerIdentificationToken($server));
        $this->dbHelper->merge($server);
        $this->dbHelper->flush($server);

        return $this->render('panel/index', [
            'user' => $user,
            'message' => 'server added',
            'title' => 'Helio Panel'
        ]);
    }


    /**
     *
     * @return ResponseInterface
     *
     * @throws \Exception
     *
     * @Route("", methods={"GET"}, name="panel.index")
     */
    public function indexAction(): ResponseInterface
    {
        /** @var User $user */
        $user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);

        if (!$user->isActive()) {
            $user->setActive(true);
            $this->dbHelper->merge($user);
            $this->dbHelper->flush();
        }

        return $this->render('panel/index', [
            'user' => $user,
            'title' => 'Helio Panel'
        ]);
    }
}