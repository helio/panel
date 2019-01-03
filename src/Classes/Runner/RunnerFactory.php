<?php
namespace Helio\Panel\Runner;

use Helio\Panel\Model\Instance;

class RunnerFactory {

    /** @var array<RunnerInterface> */
    protected static $instances = [];

    /**
     * @param Instance $instance
     *
     * @return RunnerInterface
     */
    public static function getRunnerForInstance(Instance $instance): RunnerInterface {
        $type = ucfirst(strtolower($instance->getRunnerType()));

        if (!array_key_exists($instance->getId(), self::$instances)) {
            $className = "\\Helio\\Panel\\Runner\\$type";
            self::$instances[$instance->getId()] = new $className($instance);

        }

        return self::$instances[$instance->getId()];
    }
}