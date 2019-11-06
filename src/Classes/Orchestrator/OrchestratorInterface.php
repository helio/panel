<?php

namespace Helio\Panel\Orchestrator;

use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;

interface OrchestratorInterface
{
    public function __construct(Instance $server, Job $job = null);

    public function inspect(): ?string;

    public function getInventory(): ?string;

    public function dispatchJob(): bool;

    public function updateJob(array $jobIDs): void;

    public function startComputing(): ?string;

    public function stopComputing(): ?string;

    public function provisionManager(): void;

    public function removeManager(): bool;

    public function removeInstance(): ?string;

    public function dispatchReplicas(array $executionsWithNewReplicaCount): ?string;

    public function removeExecution(Execution $execution): string;

    public function nodeCleanup(): ?string;
}
