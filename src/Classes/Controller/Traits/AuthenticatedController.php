<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
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
        $this->user = $this->dbHelper->getRepository(User::class)->find($this->jwt['uid']);
        return true;
    }

    /**
     * Persist
     */
    protected function persistUser(): void {
        $this->dbHelper->persist($this->user);
        $this->dbHelper->flush($this->user);
    }
}