<?php

namespace Helio\Panel\Controller\Traits;

/**
 * Trait AuthorizedAdminController
 * @package Helio\Panel\Controller\Traits
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