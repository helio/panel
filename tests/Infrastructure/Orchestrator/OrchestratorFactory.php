<?php

namespace Helio\Test\Infrastructure\Orchestrator;

class OrchestratorFactory extends \Helio\Panel\Orchestrator\OrchestratorFactory
{
    public static function resetInstances(): void
    {
        self::$instances = [];
    }
}
