<?php

namespace Helio\Panel\Controller\Traits;

trait TypeApiController
{
    protected function getReturnType(): ?string
    {
        return 'json';
    }

    protected function getMode(): ?string
    {
        return 'api';
    }

    protected function getContext(): ?string
    {
        return 'panel';
    }
}
