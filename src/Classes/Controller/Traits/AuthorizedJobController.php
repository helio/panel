<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Model\User;
use Helio\Panel\Utility\JwtUtility;

/**
 * Trait ServerController
 * @package Helio\Panel\Controller\Traits
 */
trait AuthorizedJobController
{

    use AuthenticatedController;
    use JobController;

    /**
     * @return bool
     *
     */
    public function validateJob(): bool
    {
        // server has to be owned by current user or authenticated by jwt token
        return ($this->user->getId() === $this->job->getOwner()->getId())
            || (!$this->user && JwtUtility::verifyJobIdentificationToken($this->job, filter_var($this->params['token'], FILTER_SANITIZE_STRING)));
    }
}