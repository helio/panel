<?php

namespace Helio\Test\Infrastructure\Utility;

class ServerUtility extends \Helio\Panel\Utility\ServerUtility
{
    public static function resetLastExecutedCommand(): void
    {
        self::$lastExecutedShellCommand = '';
    }
}