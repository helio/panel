<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;

/**
 * Trait ServerController
 * @package Helio\Panel\Controller\Traits
 * @method User getUser()
 * @method bool hasUser()
 */
trait AuthorizedInstanceController
{

    use AuthenticatedController;
    use InstanceController;

    /**
     * @return bool
     */
    public function validateServer(): bool
    {
        // server has to be owned by current user or authenticated by jwt token
        return ($this->user->getId() === $this->instance->getOwner()->getId())
            || (!$this->user && JwtUtility::verifyInstanceIdentificationToken($this->instance, filter_var($this->params['token'], FILTER_SANITIZE_STRING)));
    }
}