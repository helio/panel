<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Exception\HttpException;
use Slim\Http\StatusCode;

trait AuthorizedAdminController
{
    use ModelUserController;

    public function validateUserAsAdmin(): void
    {
        if ($this->user->isAdmin()) {
            return;
        }
        throw new HttpException(StatusCode::HTTP_FORBIDDEN, 'Insufficient permissions');
    }
}
