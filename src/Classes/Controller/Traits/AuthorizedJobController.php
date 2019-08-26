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
        if ($this->job && null === $this->job->getId()) {
            return true;
        }
        // job has to be owned by current user or authenticated by jwt token
        return $this->job && $this->user &&
            ($this->user->isAdmin() || $this->user->getId() === $this->job->getOwner()->getId())
        ;
    }
}
