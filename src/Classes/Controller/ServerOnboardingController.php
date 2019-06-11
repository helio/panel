<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Controller\Traits\ParametrizedController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Master\MasterFactory;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\MailUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class ServerOnboardingController
 *
 * This is a special little snowflake controller that provides an API directly used by the setup-script.
 * Therefore, it has some manual and "custom" stuff in it that you won't find in any other 'proper' controller
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 * @deprecated this controller should be moved to a proper API
 *
 * @RoutePrefix('/server')
 */
class ServerOnboardingController extends AbstractController
{

    use ParametrizedController;
    use TypeApiController;

    public function getContext(): ?string
    {
        return 'panel';
    }


    /**
     * (wenn der Token bekannt ist (zB wenn der Server im Panel erstellt worden ist))
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @Route("/register", methods={"POST"}, name="server.register")
     */
    public function registerAction(): ResponseInterface
    {

        try {
            $this->requiredParameterCheck(['token' => FILTER_SANITIZE_STRING]);
            $this->optionalParameterCheck(['fqdn' => FILTER_SANITIZE_STRING]);
            $ip = filter_var(ServerUtility::getClientIp(), FILTER_VALIDATE_IP);
            /** @var Instance $server */
            $server = $this->dbHelper->getRepository(Instance::class)->findOneByToken($this->params['token']);

            if (!$server || !JwtUtility::verifyInstanceIdentificationToken($server, $this->params['token'])) {
                throw new \RuntimeException('server could not be verified', 1530915652);
            }
            if (!$server->getOwner() || !$server->getOwner()->isActive()) {
                throw new \RuntimeException('User isn\'t valid or activated', 1531254673);
            }

            if (\array_key_exists('fqdn', $this->params) && $this->params['fqdn']) {
                $server->setFqdn($this->params['fqdn']);
            }
            if (!$server->getFqdn()) {
                throw new \RuntimeException('FQDN of your server not found. please pass it as argument.', 1531339382);
            }

            $server->setIp($ip);
            $server->setStatus(InstanceStatus::CREATED);
            $this->dbHelper->merge($server);
            $this->dbHelper->flush();

            $token = MasterFactory::getMasterForInstance($server)->doSign();
            if (!$token) {
                throw new \RuntimeException('couldn\'t generate autosign. Please try again.', 1530917143);
            }
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'reason' => $e->getMessage()], StatusCode::HTTP_NOT_ACCEPTABLE);
        }

        return $this->json(['success' => true,
            'user_id' => $server->getOwner()->getId(),
            'server_id' => $server->getId(),
            'token' => $token]);
    }


    /**
     * (wenn's den User noch nicht gibt)
     *
     * @return ResponseInterface
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @Route("/init", methods={"POST"}, name="server.init")
     */
    public function createAction(): ResponseInterface
    {
        try {
            $this->requiredParameterCheck([
                'email' => [FILTER_SANITIZE_EMAIL],
                'fqdn' => [FILTER_SANITIZE_STRING],
            ]);

            $server = new Instance();
            $server->setFqdn($this->params['fqdn']);
            $server->setName('Automatically generated');
            $server->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()));
            $server->setStatus(InstanceStatus::INIT);

            /** @var User $user */
            $user = $this->dbHelper->getRepository(User::class)->findOneByEmail($this->params['email']);
            if ($user) {
                if (!$user->isActive()) {
                    throw new \InvalidArgumentException('User already exists. Please confirm by clicking the link you received via email.',
                        1531251350);
                }

                $server->setOwner($user);
                $this->dbHelper->persist($server);
                $this->dbHelper->merge($user);
                $this->dbHelper->flush();
                $server->setToken(JwtUtility::generateInstanceIdentificationToken($server));
                $this->dbHelper->merge($server);
                $this->dbHelper->flush();

                return $this->json(['success' => true, 'reason' => 'User already confirmed'], StatusCode::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE);
            }
            $user = new User();
            $user->setEmail($this->params['email'])->setCreated();
            $server->setOwner($user);
            $this->dbHelper->persist($user);
            $this->dbHelper->flush();
            $user->setToken(JwtUtility::generateUserIdentificationToken($user));
            $this->dbHelper->persist($user);
            $server->setToken(JwtUtility::generateInstanceIdentificationToken($server));
            $this->dbHelper->merge($server);
            $this->dbHelper->flush($server);

            if (!$this->zapierHelper->submitUserToZapier($user)) {
                throw new \RuntimeException('Error during user creation', 1531253379);
            }
            if (!MailUtility::sendConfirmationMail($user, '+5 minutes')) {
                throw new \RuntimeException('Couldn\'t send confirmation mail', 1531253400);
            }
        } catch (\Exception $e) {
            LogHelper::err('Error during Server init: ' . $e->getMessage());
            return $this->json(['success' => false, 'reason' => $e->getMessage()], StatusCode::HTTP_NOT_ACCEPTABLE);
        }

        return $this->json([
            'success' => true,
            'user_id' => $user->getId(),
            'server_id' => $server->getId(),
            'message' => 'User and Server created. Please confirm by klicking the link you just received by email.'
        ], StatusCode::HTTP_OK);
    }


    /**
     * (wenn's den User schon gibt)
     *
     * @return ResponseInterface
     *
     * @Route("/gettoken", methods={"POST"}, name="server.gettoken")
     */
    public function getTokenAction(): ResponseInterface
    {
        try {
            $this->requiredParameterCheck([
                'email' => [FILTER_SANITIZE_EMAIL],
                'fqdn' => [FILTER_SANITIZE_STRING],
            ]);
            $ip = filter_var(ServerUtility::getClientIp(), FILTER_VALIDATE_IP);

            /** @var User $user */
            $user = $this->dbHelper->getRepository(User::class)->findOneByEmail($this->params['email']);

            if (!$user) {
                throw new \InvalidArgumentException('User not found', StatusCode::HTTP_FORBIDDEN);
            }

            /** @var Instance $server */
            $server = $this->dbHelper->getRepository(Instance::class)->findOneByFqdn($this->params['fqdn']);
            if (!$server) {
                $server = new Instance();
                $server->setFqdn($this->params['fqdn'])
                    ->setName('Automatically generated during gettoken')
                    ->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()))
                    ->setStatus(InstanceStatus::INIT)
                    ->setIp($ip)
                    ->setOwner($user);
                $this->dbHelper->persist($server);
                $this->dbHelper->flush();
                $server->setToken(JwtUtility::generateInstanceIdentificationToken($server));
                $this->dbHelper->persist($server);
                $this->dbHelper->flush();
            }

            if (!$server || !$server->getOwner() || $user->getId() !== $server->getOwner()->getId()) {
                throw new \InvalidArgumentException('Instance not found or not permitted', StatusCode::HTTP_NOT_FOUND);
            }
        } catch (\Exception $e) {
            LogHelper::warn('Error at gettoken: ' . $e->getMessage() . "\nsupplied body has been:" . print_r((string)$this->request->getBody(), true));
            return $this->json(['success' => false, 'reason' => $e->getMessage()],
                $e->getCode() < 1000 ? $e->getCode() : StatusCode::HTTP_NOT_ACCEPTABLE);
        }

        return $this->json(['success' => true, 'token' => $server->getToken()], StatusCode::HTTP_OK);
    }
}