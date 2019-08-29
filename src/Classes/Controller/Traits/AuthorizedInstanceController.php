<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Exception\HttpException;
use Slim\Http\StatusCode;

trait AuthorizedInstanceController
{
    use ModelInstanceController;

    public function validateInstanceAuthorisation(): void
    {
        if ($this->instance && null === $this->instance->getId()) {
            return;
        }
        // server has to be owned by current user or authenticated by jwt token
        if ($this->instance && $this->user &&
            ($this->user->isAdmin() || $this->user->getId() === $this->instance->getOwner()->getId())) {
            return;
        }
        throw new HttpException(StatusCode::HTTP_FORBIDDEN, 'Insufficient permissions');
    }
}
