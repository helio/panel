<?php
namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;

interface DispatchableInterface {
    public function __construct(Job $job);

    public function getDispatchConfig(): DispatchConfig;

    public function getExecutionEstimates(): array;
}