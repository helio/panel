<?php

namespace Helio\Panel\Job;

class DispatchConfig
{
    protected $image = '';
    protected $envVariables = [];

    /**
     * @return string
     */
    public function getImage(): string
    {
        return $this->image;
    }

    /**
     * @param string $image
     * @return DispatchConfig
     */
    public function setImage(string $image): DispatchConfig
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return array
     */
    public function getEnvVariables(): array
    {
        return $this->envVariables;
    }

    /**
     * @param array $envVariables
     * @return DispatchConfig
     */
    public function setEnvVariables(array $envVariables): DispatchConfig
    {
        $this->envVariables = $envVariables;
        return $this;
    }

}