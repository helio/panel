<?php

namespace Helio\Panel\Controller\Traits;

use Slim\Http\Request;

/**
 * Trait TypeAutoController.
 *
 * @property Request $request
 */
trait TypeAutoController
{
    protected function getReturnType(): ?string
    {
        if ('application/json' === $this->request->getMediaType()) {
            return 'json';
        }

        return 'html';
    }

    protected function getMode(): ?string
    {
        return null;
    }
}
