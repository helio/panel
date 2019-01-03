<?php

namespace Helio\Panel\Controller\Traits;


trait TypeAutoController
{
    protected function getReturnType(): ?string
    {
        if (stripos($this->request->getContentType(), 'json') !== false) {
            return 'json';
        }
        return 'html';
    }

    protected function getMode(): ?string
    {
        return null;
    }
}