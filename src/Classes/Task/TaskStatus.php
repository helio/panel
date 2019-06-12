<?php

namespace Helio\Panel\Task;

final class TaskStatus
{
    public const UNKNOWN = 0;
    public const READY = 1;
    public const RUNNING = 2;
    public const DONE = 3;
    public const STOPPED = 9;

    public const labels = [
        'status-0' => 'Unknown',
        'status-1' => 'Ready',
        'status-2' => 'Running',
        'status-3' => 'Done',
        'status-9' => 'Interrupted'
    ];


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

    public static function isValidPendingStatus(int $status): bool
    {
        return $status === self::READY
            || $status === self::STOPPED;
    }

    public static function getLabel(int $status): string
    {
        return self::labels["status-$status"];
    }
}