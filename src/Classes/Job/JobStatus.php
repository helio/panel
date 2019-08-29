<?php

namespace Helio\Panel\Job;

use RuntimeException;

/**
 * Class JobStatus.
 *
 * @OA\Schema(
 *     schema="jobstatus",
 *     title="Job Status",
 *     type="string",
 *     description="The Status of the job",
 *     enum = {"Creating", "Ready", "Running", "Done", "Interrupted"},
 *     example="Creating"
 * )
 */
final class JobStatus
{
    public const UNKNOWN = 0;
    public const INIT = 1;
    public const READY = 2;
    public const DONE = 3;
    public const DELETED = 9;

    public const labels = [
        'status-0' => 'Unknown',
        'status-1' => 'Creating',
        'status-2' => 'Ready',
        'status-3' => 'Done',
        'status-9' => 'Deleted',
    ];

    public function __construct()
    {
        throw new RuntimeException('Cannot instantiate ' . __CLASS__);
    }

    public static function isValidStatus(int $status): bool
    {
        return self::UNKNOWN === $status
            || self::INIT === $status
            || self::READY === $status
            || self::DONE === $status
            || self::DELETED === $status;
    }

    public static function isValidActiveStatus(int $status): bool
    {
        return self::READY === $status;
    }

    public static function getLabel(int $status): string
    {
        return self::labels["status-$status"];
    }

    public static function getAllButDeletedStatusCodes(): array
    {
        return [
            self::UNKNOWN,
            self::INIT,
            self::READY,
            self::DONE,
        ];
    }

    public static function getRunningStatusCodes(): array
    {
        return [
            self::INIT,
            self::READY,
        ];
    }
}
