<?php

namespace Helio\Panel\Job;

final class JobType
{
    public const GITLAB_RUNNER = 'gitlab';
    public const ENERGY_PLUS_85 = 'ep85';
    public const UNKNOWN = '';

    /**
     * @var array
     */
    protected static $iconMap;

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

    /**
     * @param string $type
     * @return string
     */
    public static function getIconClassesForType(string $type): string
    {
        if (self::isValidType($type)) {
            $map = [
                self::ENERGY_PLUS_85 => 'fa fa-plus'
            ];

            return $map[$type] ?? "fa fa-$type";
        }
    }
}