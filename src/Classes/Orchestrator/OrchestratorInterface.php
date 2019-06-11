<?php

namespace Helio\Panel\Orchestrator;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;

interface OrchestratorInterface
{
    public function __construct(Instance $server);

    public function getInventory();

    public function dispatchJob(Job $job): bool;

    /**
     * @deprecated
     */
    public function setInitManagerNodeIp(Job $job): bool;

    /**
     * @deprecated
     */
    public function setClusterToken(Job $job): bool;

    public function provisionManager(Job $job): bool;

    public function joinWorkers(Job $job): bool;
}