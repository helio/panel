<?php
namespace Helio\Panel\Instance;

use Helio\Panel\Model\Instance;

class InstanceFactory {

    /** @var array<InstanceInterface> */
    protected static $instances = [];

    /**
     * @param Instance $instance
     *
     * @return InstanceInterface
     */
    public static function getInstanceForServer(Instance $instance): InstanceInterface {
        $type = ucfirst(strtolower($instance->getInstanceType()));

        if (!array_key_exists($instance->getId(), self::$instances)) {
            $className = "\\Helio\\Panel\\Instance\\$type";
            self::$instances[$instance->getId()] = new $className($instance);

        }

        return self::$instances[$instance->getId()];
    }
}