<?php

namespace Helio\Panel\Orchestrator;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;

interface OrchestratorInterface
{
    public function __construct(Instance $server);

    public function getInventory();

    public function dispatchJob(Job $job): bool;

    public function provisionManager(Job $job): bool;
}