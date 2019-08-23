<?php

namespace Helio\Panel\Controller\Traits;

/**
 * Trait ValidatedJobController.
 */
trait AuthorizedJobController
{
    use ModelJobController;

    /**
     * @return bool
     */
    public function validateJob(): bool
    {
        if ($this->job && $this->job->getId() === null) {
            return true;
        }
        // job has to be owned by current user or authenticated by jwt token
        return $this->job && $this->user &&
            ($this->user->isAdmin() || $this->user->getId() === $this->job->getOwner()->getId())
        ;
    }
}
