<?php
namespace Helio\Panel\Orchestrator;

use Helio\Panel\Model\Instance;

class OrchestratorFactory {

    /** @var array<OrchestratorInterface> */
    protected static $instances = [];

    /**
     * @param Instance $instance
     *
     * @return OrchestratorInterface
     */
    public static function getOrchestratorForInstance(Instance $instance): OrchestratorInterface {
        $type = ucfirst(strtolower($instance->getOrchestratorType()));

        if (!array_key_exists($instance->getId(), self::$instances)) {
            $className = "\\Helio\\Panel\\Orchestrator\\$type";
            self::$instances[$instance->getId()] = new $className($instance);

        }

        return self::$instances[$instance->getId()];
    }
}