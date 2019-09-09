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
        $command = str_replace('\\"', '"', ServerUtility::getLastExecutedShellCommand($offset));
        $pattern = '/^.*"callback":"' . str_replace('/', '\\/', ServerUtility::getBaseUrl()) . '([^"]+)"/';
        $matches = [];
        preg_match($pattern, $command, $matches);
        if (empty($matches)) {
            throw new \InvalidArgumentException(__METHOD__ . ' called with invalid offset or lastExecutedShellCommand stack', 1567762985);
        }

        return '/' . $matches[1];
    }
}
