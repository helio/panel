<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;

/**
 * Trait ValidatedJobController
 * @package Helio\Panel\Controller\Traits
 */
trait AuthorizedActiveJobController
{
    use AuthorizedJobController;

    /**
     * @return bool
     */
    public function validateJobIsActive(): bool
    {
        return JobType::isValidType($this->job->getType()) && JobStatus::isValidActiveStatus($this->job->getStatus());
    }
}