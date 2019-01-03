<?php

namespace Helio\Panel\Controller\Traits;


trait TypeDynamicController
{
    protected $mode = 'panel';
    protected $renderingContext = 'panel';
    protected $returnType = 'json';

    protected function getContext(): ?string
    {
        return $this->renderingContext;
    }

    protected function getReturnType(): ?string
    {
        return $this->returnType;
    }

    protected function getMode(): ?string
    {
        return $this->mode;
    }

    protected function setContext(string $context): void
    {
        $this->renderingContext = $context;
    }

    protected function setReturnType(string $returnType): void
    {
        $this->returnType = $returnType;
    }

    protected function setMode(string $mode): void
    {
        $this->mode = $mode;
    }
}