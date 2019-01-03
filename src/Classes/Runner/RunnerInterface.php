<?php

namespace Helio\Panel\Runner;

use Helio\Panel\Model\Instance;

interface RunnerInterface
{
    public function __construct(Instance $server);

    public function startComputing(bool $returnInsteadOfCall = false): ?string;

    public function stopComputing(bool $returnInsteadOfCall = false): ?string;

    public function inspect(bool $returnInsteadOfCall = false): ?string;
}