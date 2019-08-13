<?php

namespace Helio\Panel\Controller\Traits;

/**
 * Trait TypeDynamicController.
 */
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

    protected function setContext(string $context): self
    {
        $this->renderingContext = $context;

        return $this;
    }

    protected function setReturnType(string $returnType): self
    {
        $this->returnType = $returnType;

        return $this;
    }

    protected function setMode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }
}
