<?php

namespace Helio\Panel\Instance;

class Baremetal implements InstanceInterface
{
    public function provisionInstance(): bool
    {
        return false;
    }
}