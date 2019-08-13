<?php

namespace Helio\Panel\Instance;

class Openstack implements InstanceInterface
{
    public function provisionInstance(): bool
    {
        return false;
    }
}
