<?php

namespace Helio\Panel\Controller\Traits;


use \Slim\Http\Request;

/**
 * Trait TypeAutoController
 * @package Helio\Panel\Controller\Traits
 *
 * @property Request $request
 */
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