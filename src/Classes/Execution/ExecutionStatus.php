<?php

namespace Helio\Panel\Execution;

use RuntimeException;

/**
 * Class ExecutionStatus.
 *
 * @OA\Schema(
 *     schema="executionstatus",
 *     title="Execution Status",
 *     type="string",
 *     description="The Status of the Execution",
 *     enum = {"Creating", "Ready", "Running", "Done", "Interrupted", "Deleted by user"}
 * )
 */
final class ExecutionStatus
{
    public const UNKNOWN = 0;
    public const READY = 1;
    public const RUNNING = 2;
    public const DONE = 3;
    public const STOPPED = 9;
    public const TERMINATED = 99;

    public const labels = [
        'status-0' => 'Unknown',
        'status-1' => 'Ready',
        'status-2' => 'Running',
        'status-3' => 'Done',
        'status-9' => 'Interrupted',
        'status-99' => 'Deleted by user',
    ];

    public function __construct()
    {
        throw new RuntimeException('Cannot instantiate ' . __CLASS__);
    }

    public static function isValidStatus(int $status): bool
    {
        return self::UNKNOWN === $status
            || self::READY === $status
            || self::RUNNING === $status
            || self::DONE === $status
            || self::TERMINATED === $status
            || self::STOPPED === $status;
    }

    public static function isValidPendingStatus(int $status): bool
    {
        return self::READY === $status
            || self::STOPPED === $status;
    }

    public static function isRunning(int $status): bool
    {
        return self::RUNNING === $status;
    }

    public static function isNotRequiredToRunAnymore(int $status): bool
    {
        return self::TERMINATED === $status
            || self::DONE === $status;
    }

    public static function isFinishedExecution(int $status): bool
    {
        return static::isNotRequiredToRunAnymore($status) || self::STOPPED === $status;
    }

    public static function getLabel(int $status): string
    {
        return self::labels["status-$status"];
    }

    public static function getRunningStatusCodes(): array
    {
        return [
            self::READY,
            self::RUNNING,
        ];
    }
}
