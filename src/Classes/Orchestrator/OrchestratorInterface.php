<?php

namespace Helio\Panel\Orchestrator;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;

interface OrchestratorInterface
{
    public function __construct(Instance $server, Job $job = null);

    public function inspect();

    public function getInventory();

    public function dispatchJob(): bool;

    public function updateJob(array $jobIDs): void;

    public function startComputing();

    public function stopComputing();

    public function provisionManager(): void;

    public function removeManager(): bool;

    public function removeInstance();

    public function dispatchReplicas(array $executionsWithNewReplicaCount);
}
