<?php

namespace Helio\Panel\Controller;

use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Helper\MailHelper;
use Helio\Panel\Model\User;
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
        return $this->render('user/form', ['title' => 'Welcome!']);
    }


    /**
     *
     * @return ResponseInterface
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     *
     * @Route("user/login", methods={"POST"}, name="user.create")
     */
    public function CreateUserAction(): ResponseInterface
    {
        $email = filter_var($this->request->getParsedBodyParam('email', FILTER_SANITIZE_EMAIL));
        $user = new User();
        $user->setEmail($email);
        DbHelper::get()->persist($user);
        DbHelper::get()->flush($user);

        return $this->render('user/create',
            [
                'success' => $this->submitUserToZapier($user) && MailHelper::sendConfirmationMail($user)
            ]
        );
    }

}