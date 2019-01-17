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


    /**
     * @param string $selected
     * @return string
     */
    public static function getOptionList(string $selected = ''): string
    {
        $map = [
            self::VM => 'Virtual Machine',
            self::BAREMETAL => 'Physical Machine',
            self::OPENSTACK => 'OpenStack',
            self::BEARCLOUD => 'Bear Cloud'
        ];

        $return = '';
        foreach ($map as $key => $label) {
            $selPart = $selected === $key ? 'selected' : '';
            $return .= "<option value=\"$key\" $selPart>$label</option>";
        }
        return $return;
    }


    /**
     * @param string $type
     * @return string
     */
    public static function getIcon(string $type = ''): string
    {
        $map = [
            self::VM => 'pficon pficon-container-node',
            self::BAREMETAL => 'pficon pficon-container-node',
            self::OPENSTACK => 'pficon pficon-container-node',
            self::BEARCLOUD => 'pficon pficon-container-node'
        ];

        return $map[$type] ?? 'pficon pficon-container-node';
    }
}