<?php

namespace Helio\Panel\Controller\Traits;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Exception\HttpException;
use Helio\Panel\Model\User;
use Slim\Http\StatusCode;

trait ModelUserController
{
    /**
     * @var User
     */
    protected $user;

    /**
     * @return bool
     *
     * @throws Exception
     */
    public function setupUser(): bool
    {
        $this->user = App::getApp()->getContainer()->get('user');

        return true;
    }

    /**
     * @throws Exception
     */
    public function validateUserIsSet(): void
    {
        if ($this->user) {
            $this->persistUser();

            return;
        }

        throw new HttpException(StatusCode::HTTP_NOT_FOUND, 'No user found');
    }

    /**
     * Persist.
     *
     * @throws Exception
     */
    protected function persistUser(): void
    {
        if ($this->user) {
            $this->user->setLatestAction();

            App::getDbHelper()->persist($this->user);
            App::getDbHelper()->flush($this->user);
        }
    }
}
