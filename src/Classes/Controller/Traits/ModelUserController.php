<?php

namespace Helio\Panel\Controller\Traits;

use \Exception;
use Helio\Panel\App;
use Helio\Panel\Model\User;

trait ModelUserController
{

    /**
     * @var User
     */
    protected $user;


    /**
     * @return bool
     * @throws Exception
     */
    public function setupUser(): bool
    {
        $this->user = App::getApp()->getContainer()->get('user');
        return true;
    }


    /**
     * @return bool
     * @throws Exception
     */
    public function validateUserIsSet(): bool
    {
        if ($this->user) {
            $this->persistUser();
            return true;
        }
        return false;
    }


    /**
     * Persist
     * @throws Exception
     */
    protected function persistUser(): void
    {
        if ($this->user) {
            $this->user->setActive(true)->setLatestAction();
            App::getDbHelper()->persist($this->user);
            App::getDbHelper()->flush($this->user);
        }
    }
}