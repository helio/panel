<?php

namespace Helio\Panel\Runner;

use Helio\Panel\Model\Instance;
use Helio\Panel\Utility\ServerUtility;

class Docker implements RunnerInterface
{

    /**
     * @var Instance
     */
    protected $instance;


    protected static $username = 'panel';

    private static $startComputingCommand = 'ssh %s@%s "sudo docker node update --availability active %s"';
    private static $stopComputingCommand = 'ssh %s@%s "sudo docker node update --availability drain  %s"';
    private static $restoreComputingCommand = 'ssh %s@%s "sudo docker node update --availability restore  %s"';
    private static $inspectCommand = 'ssh %s@%s "sudo docker node inspect %s"';

    /**
     * Docker constructor.
     * @param Instance $instance
     */
    public function __construct(Instance $instance)
    {
        $this->instance = $instance;
    }

    /**
     * @param bool $returnInsteadOfCall
     * @return string
     */
    public function startComputing(bool $returnInsteadOfCall = false): ?string
    {
        return ServerUtility::executeShellCommand($this->parseCommand('startComputing'), $returnInsteadOfCall);
    }


    /**
     * @param bool $returnInsteadOfCall
     * @return string
     */
    public function stopComputing(bool $returnInsteadOfCall = false): ?string
    {
        return ServerUtility::executeShellCommand($this->parseCommand('stopComputing'), $returnInsteadOfCall);
    }


    /**
     * @param bool $returnInsteadOfCall
     * @return string
     */
    public function inspect(bool $returnInsteadOfCall = false): ?string
    {
        return ServerUtility::executeShellCommand(sprintf(self::$inspectCommand, self::$username, $this->instance->getRunnerCoordinator(), $this->instance->getHostname()), $returnInsteadOfCall);
    }

    /**
     * @param string $commandName
     * @return string
     */
    protected function parseCommand(string $commandName): string
    {
        ServerUtility::validateParams([self::$username, $this->instance->getRunnerCoordinator(), $this->instance->getFqdn()]);

        $commandName .= 'Command';
        return sprintf(self::$$commandName, self::$username, $this->instance->getRunnerCoordinator(), $this->instance->getFqdn());
    }
}