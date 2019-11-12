<?php

namespace Helio\Test\Infrastructure\Helper;

use Helio\Test\Infrastructure\Utility\ServerUtility;

class TestHelper
{
    /**
     * @param  int    $offset
     * @return string
     */
    public static function getCallbackUrlFromExecutedShellCommand(int $offset = 0): string
    {
        $command = self::unescapeChoriaCommand($offset);
        $pattern = '/^.*"callback":"' . str_replace('/', '\\/', ServerUtility::getBaseUrl()) . '([^"]+)"/';
        $matches = [];
        preg_match($pattern, $command, $matches);
        if (empty($matches)) {
            throw new \InvalidArgumentException(__METHOD__ . ' called with invalid offset or lastExecutedShellCommand stack', 1567762985);
        }

        return '/' . $matches[1];
    }

    public static function getInputFromChoriaCommand(int $offset = 0): array
    {
        $command = self::unescapeChoriaCommand($offset);
        $matches = [];
        preg_match("/--input '([^']+)'/", $command, $matches);

        return \GuzzleHttp\json_decode($matches[1], true);
    }

    public static function unescapeChoriaCommand(int $offset = 0): string
    {
        return str_replace(['\\\"', '\\"'], ['\\"', '"'], ServerUtility::getLastExecutedShellCommand($offset));
    }
}
