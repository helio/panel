<?php

namespace Helio\Panel\Orchestrator;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;

interface OrchestratorInterface
{
    public function __construct(Instance $server, Job $job = null);

    public function inspect();

    public function getInventory();

    public function dispatchJob(Job $job = null): bool;

    public function startComputing();

    public function stopComputing();

    public function provisionManager(Job $job = null): bool;

    public function removeManager(Job $job = null): bool;

    public function removeInstance();
}
