<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\App;
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
     * overwrite setupUser to make it possible to authenticate with a job-token only
     *
     * @return bool
     */
    public function setupUser(): bool
    {
        try {
            $this->user = App::getApp()->getContainer()['user'];
        } catch (\Exception $e) {
            // don't return yet, we might be able to auth the user via job after all.
        }
        if ($this->user) {
            return true;
        }

        try {
            $this->setupJob();
            if ($this->job) {
                // note: This is safe to do since validateJob will fail if the job wasn't properly authorized
                $this->user = $this->job->getOwner();
            }
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

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