<?php

namespace Helio\Panel\Controller\Traits;

/**
 * Trait AuthorizedAdminController.
 */
trait AuthorizedAdminController
{
    use ModelUserController;

    /**
     * @return bool
     */
    public function validateUserAsAdmin(): bool
    {
        return $this->user->isAdmin();
    }
}
