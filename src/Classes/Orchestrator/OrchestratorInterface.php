<?php

namespace Helio\Panel\Orchestrator;

use Helio\Panel\Model\Instance;

interface OrchestratorInterface
{
    public function __construct(Instance $server);

    public function getInventory();
}