<?php

namespace Helio\Panel\Controller\Traits;

/**
 * Trait AuthorizedInstanceController.
 */
trait AuthorizedInstanceController
{
    use ModelInstanceController;

    /**
     * @return bool
     */
    public function validateInstanceAuthorisation(): bool
    {
        // server has to be owned by current user or authenticated by jwt token
        return $this->instance && $this->user &&
            ($this->user->isAdmin() || $this->user->getId() === $this->instance->getOwner()->getId())
        ;
    }
}
