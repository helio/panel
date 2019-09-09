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
    public const INIT_ERROR = 11;
    public const READY = 2;
    public const READY_PAUSED = 21;
    public const READY_PAUSING = 22;
    public const DONE = 3;
    public const DELETED = 9;
    public const DELETING_ERROR = 91;
    public const DELETING = 92;

    public const labels = [
        'status-0' => 'Unknown',
        'status-1' => 'Creating',
        'status-11' => 'Error during creation; awaiting redeployment.',
        'status-2' => 'Ready',
        'status-21' => 'Job cluster was removed due to underutilisation. Can be executed, but may take longer.',
        'status-22' => 'Job cluster removal is pending.',
        'status-3' => 'Done',
        'status-9' => 'Deleted',
        'status-91' => 'Error during deletion; awaiting retry.',
        'status-92' => 'Deleting',
        'status-99' => 'Deleting in progress',
    ];

    public function __construct()
    {
        throw new RuntimeException('Cannot instantiate ' . __CLASS__);
    }

    public static function isValidStatus(int $status): bool
    {
        return self::UNKNOWN === $status
            || self::INIT === $status
            || self::INIT_ERROR === $status
            || self::READY === $status
            || self::READY_PAUSED === $status
            || self::READY_PAUSING === $status
            || self::DONE === $status
            || self::DELETED === $status
            || self::DELETING_ERROR === $status
            || self::DELETING === $status;
    }

    public static function isValidActiveStatus(int $status): bool
    {
        return self::READY === $status || self::READY_PAUSED === $status;
    }

    public static function getLabel(int $status): string
    {
        return self::labels["status-$status"];
    }

    public static function getAllButDeletedAndUnknownStatusCodes(): array
    {
        return [
            self::INIT,
            self::INIT_ERROR,
            self::READY,
            self::READY_PAUSED,
            self::READY_PAUSING,
            self::DONE,
        ];
    }

    public static function getRunningStatusCodes(): array
    {
        return [
            self::INIT,
            self::READY,
            self::READY_PAUSED,
        ];
    }
}
