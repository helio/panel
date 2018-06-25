<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\ZapierHelper;
use Helio\Panel\Model\Server;
use Helio\Panel\Model\User;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Api
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/')
 */
class ApiController extends AbstractPanelController
{

    /**
     * @param $name
     *
     * @return ResponseInterface
     *
     * @Route("hello/{name:[\w]+}", methods={"GET"}, name="home.hello")
     */
    public function TestAction($name): ResponseInterface
    {
        $this->logger->addNotice('entered apiController->HelloAction');

        return $this->response->withJson(array ('name' => $name));
    }


    /**
     * @param $name
     *
     * @return ResponseInterface
     * @throws \Doctrine\ORM\ORMException
     *
     * @Route("user/create/{name:[\w]+}", "methods={"GET"}, name="user.name")
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function AddUserAction($name): ResponseInterface
    {
        $this->logger->addNotice('creating user');

        $user = new User();
        $server = new Server();
        $server->setName('testserver');
        $user->setName($name);
        $user->setEmail($name . '@blah.com');
        $user->addServer($server);
        DbHelper::get()->persist($user);
        $server->setOwner($user);
        DbHelper::get()->persist($server);
        DbHelper::get()->flush();

        // TODO: submit proper email to zapier
        $publicUserObject = json_encode([
            'name' => $user->getName(),
            'email' => 'we don\'t want zapier adding users all the time', //$user->getEmail(),
            'idHash' => sha1($user->getId())
        ]);

        $result = ZapierHelper::get()->exec('POST', $this->zapierHookUrl, ['body' => $publicUserObject]);
        $success = ($result->getStatusCode() === 200 && json_decode($result->getBody()['status'] === 'success'));

        return $this->response->withJson(array ('success' => $success));
    }
}