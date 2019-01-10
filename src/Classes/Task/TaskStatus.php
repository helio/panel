<?php

namespace Helio\Panel\Task;

final class TaskStatus
{
    public const UNKNOWN = 0;
    public const READY = 1;
    public const RUNNING = 2;
    public const DONE = 3;
    public const STOPPED = 9;


    public function __construct()
    {
        throw new \RuntimeException('Cannot instanciate ' . __CLASS__);
    }

    public static function isValidStatus(int $status): bool
    {
        return $status === self::UNKNOWN
            || $status === self::READY
            || $status === self::RUNNING
            || $status === self::DONE
            || $status === self::STOPPED;
    }
}