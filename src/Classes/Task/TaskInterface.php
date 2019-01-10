<?php

namespace Helio\Panel\Task;

use Helio\Panel\Model\Task;

interface TaskInterface
{
    public function __construct(Task $task);

    public function run(array $params);

    public function stop(array $params);
}