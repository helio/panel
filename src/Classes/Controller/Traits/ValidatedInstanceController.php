<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Utility\JwtUtility;

/**
 * Trait ValidatedInstanceController
 * @package Helio\Panel\Controller\Traits
 */
trait ValidatedInstanceController
{
    use InstanceController;


    /**
     * @return bool
     *
     */
    public function validateInstance(): bool
    {
        $this->optionalParameterCheck(['token' => FILTER_SANITIZE_STRING]);
        return $this->instance
            && (
                JwtUtility::verifyInstanceIdentificationToken($this->instance, $this->params['token'])
                || ($this->user && $this->user->getId() === $this->instance->getOwner()->getId())
            );
    }
}