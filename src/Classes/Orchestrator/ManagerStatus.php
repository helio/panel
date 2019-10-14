<?php

namespace Helio\Panel\Orchestrator;

use RuntimeException;

/**
 * Class ExecutionStatus.
 */
final class ManagerStatus
{
    public const UNKNOWN = 'unknown';
    public const READY = 'ready';
    public const REMOVED = 'removed';

    public function __construct()
    {
        throw new RuntimeException('Cannot instantiate ' . __CLASS__);
    }

    public static function isValidStatus(string $status): bool
    {
        return self::UNKNOWN === $status
            || self::READY === $status
            || self::REMOVED === $status;
    }

    public static function isValidActiveStatus(string $status): bool
    {
        return self::READY === $status;
    }
}
