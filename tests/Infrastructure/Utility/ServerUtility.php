<?php

namespace Helio\Test\Infrastructure\Utility;

class ServerUtility extends \Helio\Invest\Utility\ServerUtility
{
    public static function resetLastExecutedCommand(): void
    {
        self::$lastExecutedShellCommand = '';
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

        return '{"status":"success"}';
    }
}