<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Model\Server;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\MailUtility;
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
     * @return ResponseInterface
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @Route("/create", methods={"POST"}, name="server.register")
     */
    public function createAction(): ResponseInterface
    {
        try {
            $params = json_decode($this->request->getBody());
            if (!$params || !isset($params->email, $params->fqdn)) {
                throw new \InvalidArgumentException('No proper data submitted', 1531251031);
            }
            $email = filter_var(filter_var($params->email, FILTER_SANITIZE_EMAIL), FILTER_VALIDATE_EMAIL);
            $fqdn = filter_var(filter_var($params->fqdn, FILTER_SANITIZE_STRING), FILTER_VALIDATE_DOMAIN);
            $ip = filter_var(ServerUtility::getClientIp(), FILTER_VALIDATE_IP);

            if (!$email || !$fqdn || !$ip) {
                throw new \InvalidArgumentException('Please pass valid values', 1531258313);
            }

            $user = $this->dbHelper->getRepository(User::class)->findOneByEmail($email);
            if ($user) {
                throw new \InvalidArgumentException('User already exists', 1531251350);
            }
            $user = new User();
            $user->setEmail($email);
            $server = new Server();
            $server->setFqdn($fqdn);
            $server->setCreated(new \DateTime('Europe/Berlin'));
            $server->setOwner($user);
            $this->dbHelper->persist($user);
            $this->dbHelper->flush();
            $server->setToken(JwtUtility::generateServerIdentificationToken($server));
            $this->dbHelper->merge($server);
            $this->dbHelper->flush($server);

            if (!$this->zapierHelper->submitUserToZapier($user)) {
                throw new \RuntimeException('', 1531253379);
            }
            if (!MailUtility::sendConfirmationMail($user)) {
                throw new \RuntimeException('Couldn\'t send confirmation mail', 1531253400);
            }
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'reason' => $e->getMessage()], 406);
        }
        $this->response->getBody()->write('User and Server created. Please confirm by klicking the link you just received by email.');

        return $this->response;
    }


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
            if (!$server->getOwner() || !$server->getOwner()->isActive()) {
                throw new \RuntimeException('User isn\'t valid or activated', 1531254673);
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
            return $this->json(['success' => false, 'reason' => $e->getMessage()], 406);
        }

        $this->response->getBody()->write($return);

        return $this->response;
    }
}