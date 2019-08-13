<?php

namespace Helio\Panel\Orchestrator;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;

class OrchestratorFactory
{
    /** @var array<OrchestratorInterface> */
    protected static $instances = [];

    /**
     * @param Instance $instance
     * @param Job|null $job
     *
     * @return OrchestratorInterface
     */
    public static function getOrchestratorForInstance(Instance $instance, Job $job = null): OrchestratorInterface
    {
        $type = ucfirst(strtolower($instance->getOrchestratorType()));
        $identifier = $instance->getId() . '-' . ($job ? $job->getId() : '');

        if (!array_key_exists($identifier, self::$instances)) {
            $className = "\\Helio\\Panel\\Orchestrator\\$type";
            self::$instances[$identifier] = new $className($instance, $job);
        }

        return self::$instances[$identifier];
    }
}
