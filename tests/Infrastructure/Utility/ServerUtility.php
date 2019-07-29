<?php

namespace Helio\Test\Infrastructure\Utility;

class ServerUtility extends \Helio\Panel\Utility\ServerUtility
{
    public static function resetLastExecutedCommand(): void
    {
        self::$lastExecutedShellCommand = [];
    }

    /**
     * Mock command results
     *
     * @param string $command
     * @return string
     */
    public static function getMockResultForShellCommand(string $command) : string {

        if (strpos($command, 'RemoteManagers') && strpos($command, 'Addr') !== false) {
            return '5.1.2.3';
        }
        if (strpos($command, 'docker::swarm_token')) {
            return '{"_output":"token"}';
        }
        if (strpos($command, '{{.ID}}')) {
            return 'Badklb4lbaDKbasibo4VB';
        }
        if (strpos($command, 'node inspect')) {
            return '{["status":"dummy"]}';
        }

        return '{"status":"success"}';
    }
}