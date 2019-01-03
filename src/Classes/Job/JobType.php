<?php

namespace Helio\Panel\Job;

final class JobType
{
    public const GITLAB_RUNNER = 'gitlab';
    public const UNKNOWN = '';

    public function __construct()
    {
        throw new \RuntimeException('Cannot instanciate ' . __CLASS__);
    }

    public static function isValidType(string $type): bool
    {
        return $type === self::GITLAB_RUNNER
            || $type === self::UNKNOWN;
    }
}