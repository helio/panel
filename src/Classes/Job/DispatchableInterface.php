<?php

namespace Helio\Panel\Job;

use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;

interface DispatchableInterface
{
    public function __construct(Job $job, Execution $execution = null);

    public function getDispatchConfig(): DispatchConfig;

    public function getExecutionEstimates(): array;

    public function isExecutionStillAffordable(): bool;
}
