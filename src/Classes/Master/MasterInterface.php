<?php

namespace Helio\Panel\Master;

use Helio\Panel\Model\Instance;

interface MasterInterface
{
    public function __construct(Instance $instance);

    public function getStatus();

    public function doSign();
}
