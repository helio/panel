<?php

namespace Helio\Panel\Controller;

use Exception;
use Helio\Panel\Response\DefaultResponse;
use Helio\Panel\Response\TokenResponse;
use InvalidArgumentException;
use RuntimeException;
use DateTime;
use GuzzleHttp\Exception\GuzzleException;
use Helio\Panel\App;
use Helio\Panel\Controller\Traits\ModelParametrizedController;
use Helio\Panel\Controller\Traits\TypeBrowserController;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Instance\InstanceStatus;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\CookieUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\NotificationUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Everything that requires no authentication goes here.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/')
 */
class DefaultController extends AbstractController
{
    use ModelParametrizedController;
    use TypeBrowserController;

    protected function getMode(): ?string
    {
        return 'default';
    }

    /**
     * @return ResponseInterface
     * @Route("", methods={"GET"})
     */
    public function LoginAction(): ResponseInterface
    {
        $token = $this->request->getCookieParam('token', null);
        if ($token) {
            return $this->response->withRedirect('/panel', StatusCode::HTTP_FOUND);
        }

        return $this->render(['title' => 'Welcome!']);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("confirm", methods={"GET"})
     */
    public function ConfirmAction(): ResponseInterface
    {
        $this->requiredParameterCheck(['signature' => FILTER_SANITIZE_STRING]);

        return CookieUtility::addCookie($this->response->withRedirect('/panel', StatusCode::HTTP_FOUND), 'token', $this->params['signature']);
    }

    /**
     * @return ResponseInterface
     * @Route("loggedout", methods={"GET"})
     */
    public function LoggedoutAction(): ResponseInterface
    {
        return CookieUtility::deleteCookie($this->render(['title' => 'Good Bye', 'loggedOut' => true]), 'token');
    }

    /**
     * @return ResponseInterface
     *
     * @throws GuzzleException
     * @throws Exception
     *
     * @Route("user/login", methods={"POST"}, name="user.submit")
     */
    public function SubmitUserAction(): ResponseInterface
    {
        // normal user process
        $this->requiredParameterCheck(['email' => FILTER_SANITIZE_EMAIL]);

        $user = App::getDbHelper()->getRepository(User::class)->findOneBy(['email' => $this->params['email']]);
        if (!$user) {
            $user = new User();
            $user->setEmail($this->params['email'])->setCreated();
            App::getDbHelper()->persist($user);
            App::getDbHelper()->flush();
            if (!App::getZapierHelper()->submitUserToZapier($user)) {
                throw new RuntimeException('Error during User Creation', 1546940197);
            }
        }

        if (!NotificationUtility::sendConfirmationMail($user)) {
            throw new RuntimeException('Error during User Creation', 1545655919);
        }

        // catch Demo User
        if ('email@example.com' === $user->getEmail()) {
            return $this->response->withRedirect(ServerUtility::getBaseUrl() . 'confirm?signature=' . JwtUtility::generateToken('+5 minutes', $user)['token']);
        }

        return $this->render(
            [
                'user' => $user,
                'title' => 'Login link sent',
            ]
        );
    }

    /**
     * (wenn's den User noch nicht gibt).
     *
     * @return ResponseInterface
     *
     * @throws GuzzleException
     *
     * @Route("server/init", methods={"POST"}, name="server.init")
     *
     * TODO: Merge User Creation action with above function
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
            $server->setCreated(new DateTime('now', ServerUtility::getTimezoneObject()));
            $server->setStatus(InstanceStatus::INIT);

            /** @var User $user */
            $user = App::getDbHelper()->getRepository(User::class)->findOneBy(['email' => $this->params['email']]);
            if ($user) {
                if (!$user->isActive()) {
                    throw new InvalidArgumentException(
                        'User already exists. Please confirm by clicking the link you received via email.',
                        1531251350
                    );
                }

                $server->setOwner($user);
                App::getDbHelper()->persist($server);
                App::getDbHelper()->persist($user);
                App::getDbHelper()->flush();

                return $this->json(new DefaultResponse(true, 'User already confirmed'), StatusCode::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE);
            }
            $user = new User();
            $user->setEmail($this->params['email'])->setCreated();
            $server->setOwner($user);
            App::getDbHelper()->persist($user);
            App::getDbHelper()->merge($server);
            App::getDbHelper()->flush();

            if (!App::getZapierHelper()->submitUserToZapier($user)) {
                throw new RuntimeException('Error during user creation', 1531253379);
            }
            if (!NotificationUtility::sendConfirmationMail($user, '+15 minutes')) {
                throw new RuntimeException('Couldn\'t send confirmation mail', 1531253400);
            }
        } catch (Exception $e) {
            LogHelper::err('Error during Server init: ' . $e->getMessage());

            return $this->json(DefaultResponse::fromThrowable($e), StatusCode::HTTP_NOT_ACCEPTABLE);
        }

        return $this->json([
            'success' => true,
            'user_id' => $user->getId(),
            'server_id' => $server->getId(),
            'reason' => 'User and Server created. Please confirm by klicking the link you just received by email.',
        ], StatusCode::HTTP_OK);
    }

    /**
     * (wenn's den User schon gibt).
     *
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("server/gettoken", methods={"POST"}, name="server.gettoken")
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
            $user = App::getDbHelper()->getRepository(User::class)->findOneBy(['email' => $this->params['email']]);

            if (!$user) {
                throw new InvalidArgumentException('User not found', StatusCode::HTTP_FORBIDDEN);
            }

            /** @var Instance $server */
            $server = App::getDbHelper()->getRepository(Instance::class)->findOneBy(['fqdn' => $this->params['fqdn']]);
            if (!$server) {
                $server = (new Instance());
                $server->setIp($ip)
                    ->setOwner($user)
                    ->setFqdn($this->params['fqdn'])
                    ->setName('Automatically generated during gettoken')
                    ->setCreated(new DateTime('now', ServerUtility::getTimezoneObject()))
                    ->setStatus(InstanceStatus::INIT);
                App::getDbHelper()->persist($server);
                App::getDbHelper()->flush();
            }

            if (!$server || !$server->getOwner() || $user->getId() !== $server->getOwner()->getId()) {
                throw new InvalidArgumentException('Instance not found or not permitted', StatusCode::HTTP_NOT_FOUND);
            }
        } catch (Exception $e) {
            LogHelper::warn('Error at gettoken: ' . $e->getMessage() . "\nsupplied body has been:" . print_r((string) $this->request->getBody(), true));

            return $this->json(
                new DefaultResponse(false, $e->getMessage()),
                $e->getCode() < 1000 ? $e->getCode() : StatusCode::HTTP_NOT_ACCEPTABLE
            );
        }

        return $this->json(new TokenResponse(JwtUtility::generateToken(null, $user, $server)['token']), StatusCode::HTTP_OK);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("apidoc", methods={"GET"}, name="api.doc")
     */
    public function ApiDocAction(): ResponseInterface
    {
        // exclude all files named ApiInterface.php because they are meant as single-entry points (see self::JobApiDocAction())
        return $this->renderApiDocumentation('/^(.(?!Job\/[^\/]{1,}\/ApiInterface\.php))*$/');
    }

    /**
     * @param string jobtype
     *
     * @return ResponseInterface
     *
     * @Route("apidoc/job/{jobtype:[\w]+}", methods={"GET"}, name="api.doc")
     */
    public function JobApiDocAction(string $jobtype): ResponseInterface
    {
        return $this->renderApiDocumentation(['App.php', 'Job/' . ucfirst(strtolower($jobtype)) . '/ApiInterface.php']);
    }
}
