<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Exception\HttpException;
use Slim\Http\StatusCode;

trait AuthorizedJobController
{
    use ModelJobController;

    public function validateJob(): void
    {
        if ($this->job && null === $this->job->getId()) {
            return;
        }
        // job has to be owned by current user or authenticated by jwt token
        if ($this->job && $this->user &&
            ($this->user->isAdmin() || $this->user->getId() === $this->job->getOwner()->getId())) {
            return;
        }
        throw new HttpException(StatusCode::HTTP_FORBIDDEN, 'Insufficient permissions');
    }
}
