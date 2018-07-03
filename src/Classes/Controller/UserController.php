<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\ZapierHelper;
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
        $email = filter_var($this->request->getParsedBodyParam('email', FILTER_SANITIZE_EMAIL));

        $user = $this->dbHelper->getRepository(User::class)->findOneByEmail($email);
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $this->dbHelper->persist($user);
            $this->dbHelper->flush($user);
        }

        return $this->render('user/create',
            [
                'success' => $this->zapierHelper->submitUserToZapier($user) && MailUtility::sendConfirmationMail($user),
                'title' => 'Login link sent'
            ]
        );
    }

}