<?php

namespace Helio\Panel\Instance;

final class InstanceStatus
{
    public const UNKNOWN = 0;
    public const INIT = 1;
    public const CREATED = 2;
    public const READY = 3;
    public const RUNNING = 4;


    public function __construct()
    {
        throw new \RuntimeException('Cannot instanciate ' . __CLASS__);
    }

    public static function isValidStatus(int $status): bool
    {
        return $status === self::UNKNOWN
            || $status === self::INIT
            || $status === self::CREATED
            || $status === self::READY
            || $status === self::RUNNING;
    }
}