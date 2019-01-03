<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
use Helio\Panel\Model\User;

trait AdminController
{
    use AuthenticatedController;

    /**
     * @return bool
     */
    public function validateUserAsAdmin(): bool
    {
        return $this->user->isAdmin();
    }
}