<?php

namespace Helio\Panel\Controller;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\User;
use Helio\Panel\Service\UserService;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

class ApiAuthenticationController extends AbstractController
{
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
     * @return ResponseInterface
     *
     * @Route("/api/login", methods={"POST"}, name="api.login")
     *
     * @throws Exception
     */
    public function loginAction(): ResponseInterface
    {
        try {
            $contentType = $this->request->getMediaType();
            $body = $this->request->getBody();
            if ('application/json' !== $contentType || 0 === $body->getSize()) {
                throw new \InvalidArgumentException('JSON body required');
            }

            $data = \GuzzleHttp\json_decode($body, true);
            if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Valid email is required');
            }
            $email = $data['email'];
        } catch (\Throwable $e) {
            return $this->render(['success' => false, 'error' => $e->getMessage()], StatusCode::HTTP_BAD_REQUEST);
        }

        ['user' => $user, 'token' => $token] = $this->userService->login($email);

        $this->render(['token' => $token]);
    }

    protected function getReturnType(): string
    {
        return 'json';
    }

    protected function getMode(): string
    {
        return 'api';
    }
}
