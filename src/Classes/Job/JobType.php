<?php

namespace Helio\Panel\Job;

final class JobType
{
    public const GITLAB_RUNNER = 'gitlab';
    public const ENERGY_PLUS_85 = 'ep85';
    public const UNKNOWN = '';

    public function __construct()
    {
        throw new \RuntimeException('Cannot instanciate ' . __CLASS__);
    }

    public static function isValidType(string $type): bool
    {
        return $type === self::GITLAB_RUNNER
            || $type === self::ENERGY_PLUS_85
            || $type === self::UNKNOWN;
    }
}