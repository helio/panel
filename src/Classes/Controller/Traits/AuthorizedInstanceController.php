<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;

/**
 * Trait AuthorizedInstanceController
 * @package Helio\Panel\Controller\Traits
 */
trait AuthorizedInstanceController
{

    use AuthenticatedController;
    use InstanceController;

    /**
     * @return bool
     */
    public function validateInstanceAuthorisation(): bool
    {
        // server has to be owned by current user or authenticated by jwt token
        return ($this->user->getId() === $this->instance->getOwner()->getId());
    }
}