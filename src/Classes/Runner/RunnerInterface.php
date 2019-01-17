<?php

namespace Helio\Panel\Runner;

use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Model\Instance;

interface RunnerInterface
{
    public function __construct(Instance $server);

    public function startComputing(bool $returnInsteadOfCall = false);

    public function stopComputing(bool $returnInsteadOfCall = false);

    public function remove(bool $returnInsteadOfCall = false);

    public function inspect(bool $returnInsteadOfCall = false);

    public function createConfigForJob(DispatchConfig $config): string;
}