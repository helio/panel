<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Model\User;
use Helio\Panel\Utility\MailUtility;
use Psr\Http\Message\ResponseInterface;


/**
 * Class Frontend
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/')
 */
class UserController extends AbstractController
{


    /**
     *
     * @return ResponseInterface
     * @Route("", methods={"GET"})
     */
    public function IndexAction(): ResponseInterface
    {
        $token = $this->request->getCookieParam('token', null);
        if ($token) {
            return $this->response->withRedirect('/panel', 302);
        }
        return $this->render('user/login', ['title' => 'Welcome!']);
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
        $success = true;
        $email = filter_var(filter_var($this->request->getParsedBodyParam('email', FILTER_SANITIZE_EMAIL)), FILTER_VALIDATE_DOMAIN);

        $user = $this->dbHelper->getRepository(User::class)->findOneByEmail($email);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $this->dbHelper->persist($user);
            $this->dbHelper->flush($user);
            $success = $this->zapierHelper->submitUserToZapier($user);
        }

        return $this->render('user/create',
            [
                'user' => $user,
                'success' => $success && MailUtility::sendConfirmationMail($user),
                'title' => 'Login link sent'
            ]
        );
    }

}