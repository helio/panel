<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
use Helio\Panel\App;
use Helio\Panel\Model\User;

trait AuthenticatedController
{

    /**
     * @var User
     */
    protected $user;


    /**
     * @return bool
     */
    public function setupUser(): bool
    {
        try {
            $this->user = App::getApp()->getContainer()['user'];
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Persist
     */
    protected function persistUser(): void {
        $this->user->setLatestAction();
        $this->user->setActive(true);
        $this->dbHelper->persist($this->user);
        $this->dbHelper->flush($this->user);
    }
}