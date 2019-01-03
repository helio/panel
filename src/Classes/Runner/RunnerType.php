<?php

namespace Helio\Panel\Runner;

final class RunnerType
{
    public const DOCKER = 'docker';


    public const __DEFAULT = self::DOCKER;
    public function __construct()
    {
        throw new \RuntimeException('Cannot instanciate ' . __CLASS__);
    }

    public static function isValidType(string $type): bool
    {
        return $type === self::DOCKER;
    }
}