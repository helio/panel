<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Utility\JwtUtility;

/**
 * Trait ValidatedJobController
 * @package Helio\Panel\Controller\Traits
 */
trait ValidatedJobController
{
    use JobController;

    /**
     * @return bool
     *
     */
    public function validateJob(): bool
    {
        $this->requiredParameterCheck(['token' => FILTER_SANITIZE_STRING]);
        return $this->job
            && JobType::isValidType($this->job->getType())
            && JobStatus::isValidActiveStatus($this->job->getStatus())
            && (
                JwtUtility::verifyJobIdentificationToken($this->job, $this->params['token'])
                || (
                    $this->user !== null
                    && JwtUtility::verifyUserIdentificationToken($this->user, $this->params['token'])
                    && ($this->user->isAdmin() || $this->user->getId() === $this->job->getOwner()->getId())
                )
            );
    }
}