<?php

namespace Helio\Panel\Orchestrator;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;

interface OrchestratorInterface
{
    public function __construct(Instance $server, Job $job = null);

    public function inspect(): ?string;

    public function getInventory(): ?string;

    public function dispatchJob(bool $joinWorkersCallback = false): bool;

    public function joinWorkers(bool $joinWorkersCallback = false): bool;

    public function updateJob(array $jobIDs): void;

    public function startComputing(): ?string;

    public function stopComputing(): ?string;

    public function provisionManager(): void;

    public function removeManager(): bool;

    public function removeInstance(): ?string;

    public function dispatchReplicas(array $executionsWithNewReplicaCount): ?string;

    public function removeExecutions(array $executions): string;

    public function createService(array $executions): string;
}
