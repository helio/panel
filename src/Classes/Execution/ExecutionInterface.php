<?php

namespace Helio\Panel\Execution;

use Helio\Panel\Model\Execution;

interface ExecutionInterface
{
    public function __construct(Execution $execution);

    public function run(array $params);

    public function stop(array $params);
}