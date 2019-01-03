<?php

namespace Helio\Panel\Instance;

final class InstanceType
{
    public const VM = 'vm';
    public const BAREMETAL = 'baremetal';
    public const OPENSTACK = 'openstack';
    public const BEARCLOUD = 'bearcloud';


    public const __DEFAULT = self::VM;

    public function __construct()
    {
        throw new \RuntimeException('Cannot instanciate ' . __CLASS__);
    }

    public static function isValidType(string $type): bool
    {
        return $type === self::BAREMETAL
            || $type === self::VM
            || $type === self::OPENSTACK
            || $type === self::BEARCLOUD;
    }
}