<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Model\Server;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PanelController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/server')
 *
 */
class ServerApiController extends AbstractController
{


    /**
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @Route("/register", methods={"POST"}, name="server.register")
     */
    public function registerAction(): ResponseInterface
    {

        try {
            /** @var \stdClass $params */
            $params = json_decode($this->request->getBody());
            if (!$params || !isset($params->token, $params->fqdn)) {
                throw new \InvalidArgumentException('No proper data submitted', 1530911093);
            }
            $token = filter_var($params->token, FILTER_SANITIZE_STRING);
            $fqdn = filter_var($params->fqdn, FILTER_SANITIZE_STRING);
            $ip = filter_var(ServerUtility::getClientIp(), FILTER_VALIDATE_IP);
            /** @var Server $server */
            $server = $this->dbHelper->getRepository(Server::class)->findOneByToken($token);
            if (!$server || !JwtUtility::verifyServerIdentificationToken($server, $token)) {
                throw new \RuntimeException('server could not be verified', 1530915652);
            }
            $server->setFqdn($fqdn);
            $server->setIp($ip);
            $this->dbHelper->merge($server);
            $this->dbHelper->flush();

            $return = ServerUtility::submitAutosign($fqdn);
            if (!$return) {
                throw new \RuntimeException('coudldn\'t generate autosign. Please try again.', 1530917143);
            }
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'reason' => $e->getMessage()], 403);
        }

        $this->response->getBody()->write($return);
        return $this->response;
    }
}