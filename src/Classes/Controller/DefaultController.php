<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Controller\Traits\ParametrizedController;
use Helio\Panel\Controller\Traits\TypeBrowserController;
use Helio\Panel\Model\User;
use Helio\Panel\Utility\CookieUtility;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\MailUtility;
use Helio\Panel\Utility\ServerUtility;
use OpenApi\Annotations\Server;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;


/**
 * Class Frontend
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/')
 */
class DefaultController extends AbstractController
{
    use ParametrizedController;
    use TypeBrowserController;


    protected function getMode(): ?string
    {
        return 'default';
    }

    /**
     *
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
     *
     * @return ResponseInterface
     * @Route("loggedout", methods={"GET"})
     */
    public function LoggedoutAction(): ResponseInterface
    {
        return CookieUtility::deleteCookie($this->render(['title' => 'Good Bye', 'loggedOut' => true]), 'token');
    }


    /**
     *
     * @return ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     *
     * @Route("user/login", methods={"POST"}, name="user.submit")
     */
    public function SubmitUserAction(): ResponseInterface
    {
        // catch Demo User
        if (\array_key_exists('email', $this->params) && $this->params['email'] === 'email@example.com') {
            /** @var User $user */
            $user = $this->dbHelper->getRepository(User::class)->findOneByEmail('email@example.com');
            return $this->response->withRedirect(ServerUtility::getBaseUrl() . 'panel?token=' . JwtUtility::generateToken($user->getId(), '+5 minutes')['token']);
        }

        // normal user process
        $this->requiredParameterCheck(['email' => FILTER_SANITIZE_EMAIL]);

        $user = $this->dbHelper->getRepository(User::class)->findOneByEmail($this->params['email']);
        if (!$user) {
            $user = new User();
            $user->setEmail($this->params['email'])->setCreated();
            $this->dbHelper->persist($user);
            $this->dbHelper->flush($user);
            if (!$this->zapierHelper->submitUserToZapier($user)) {
                throw new \RuntimeException('Error during User Creation', 1546940197);
            }
        }

        if (!MailUtility::sendConfirmationMail($user, $this->request->getParsedBodyParam('permanent') === 'on' ? '+30 days' : '+1 week')) {
            throw new \RuntimeException('Error during User Creation', 1545655919);
        }

        return $this->render(
            [
                'user' => $user,
                'title' => 'Login link sent'
            ]
        );
    }

    /**
     * @param string $context
     * @return ResponseInterface
     *
     * @Route("apidoc/{context:[\w]+}", methods={"GET"}, name="api.doc")
     */
    public function ApiDocAction(string $context): ResponseInterface
    {
        return $this->renderApiDocumentation(['Controller'], ['Api' . ucfirst(strtolower($context)) . 'Controller.php']);
    }

    /**
     * @param string jobtype
     * @return ResponseInterface
     *
     * @Route("apidoc/job/{jobtype:[\w]+}", methods={"GET"}, name="api.doc")
     */
    public function JobApiDocAction(string $jobtype): ResponseInterface
    {
        return $this->renderApiDocumentation(['Job'], ['JobInterface.php', ucfirst(strtolower($jobtype)) . '/ApiInterface.php']);
    }


    /**
     * @param array $folder
     * @param array $include
     * @return ResponseInterface
     *
     * TODO: test this, it's quite pecular and only used for apidoc so far
     */
    protected function renderApiDocumentation(array $folder = [], array $include = []): ResponseInterface
    {
        $path = ServerUtility::getClassesPath($folder);
        $exclude = [];
        if (!empty($include)) {
            $exclude = array_filter(ServerUtility::getAllFilesInFolder($path, '.php'), function ($object) use ($include, $path) {
                $filenameInsidePath = substr($object, \strlen($path . DIRECTORY_SEPARATOR));
                return !\in_array($filenameInsidePath, $include, true);
            });
        }
        $openapi = \OpenApi\scan($path, ['exclude' => $exclude]);

        if ((array_key_exists('format', $this->params) && $this->params['format'] === 'json')
            || $this->request->getHeader('Content-Type') === 'application/json') {
            return $this->rawJson($openapi->toJson());
        }

        return $this->rawYaml($openapi->toYaml());
    }
}