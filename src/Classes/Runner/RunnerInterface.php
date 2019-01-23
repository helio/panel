<?php

namespace Helio\Panel\Runner;

use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Model\Instance;

interface RunnerInterface
{
    public function __construct(Instance $server);

    public function startComputing();

    public function stopComputing();

    public function remove();

    public function inspect();

    public function createConfigForJob(DispatchConfig $config): string;
}