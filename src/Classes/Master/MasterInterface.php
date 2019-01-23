<?php

namespace Helio\Panel\Master;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;

interface MasterInterface
{
    public function __construct(Instance $instance);

    public function getStatus();

    public function doSign();

    public function cleanup();

    public function dispatchJob(Job $job): bool;
}