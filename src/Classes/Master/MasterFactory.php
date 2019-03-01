<?php
namespace Helio\Panel\Master;

use Helio\Panel\Model\Instance;

class MasterFactory {

    /** @var array<MasterInterface> */
    protected static $instances = [];

    /**
     * @param Instance $instance
     *
     * @return MasterInterface
     */
    public static function getMasterForInstance(Instance $instance): MasterInterface {
        $type = ucfirst(strtolower($instance->getMasterType()));

        if (!array_key_exists($instance->getId() ?? 0, self::$instances)) {
            $className = "\\Helio\\Panel\\Master\\$type";
            self::$instances[$instance->getId() ?? 0] = new $className($instance);

        }

        return self::$instances[$instance->getId() ?? 0];
    }
}