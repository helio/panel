<?php

namespace Helio\Panel\Master;

final class MasterType
{
    public const PUPPET = 'puppet';


    public const __DEFAULT = self::PUPPET;
    public function __construct()
    {
        throw new \RuntimeException('Cannot instanciate ' . __CLASS__);
    }

    public static function isValidType(string $type): bool
    {
        return $type === self::PUPPET;
    }
}