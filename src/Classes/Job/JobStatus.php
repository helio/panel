<?php

namespace Helio\Panel\Job;

final class JobStatus
{
    public const UNKNOWN = 0;
    public const INIT = 1;
    public const READY = 2;


    public function __construct()
    {
        throw new \RuntimeException('Cannot instanciate ' . __CLASS__);
    }

    public static function isValidStatus(int $status): bool
    {
        return $status === self::UNKNOWN
            || $status === self::INIT
            || $status === self::READY;
    }

    public static function isValidActiveStatus(int $status): bool
    {
        return $status === self::READY;
    }
}