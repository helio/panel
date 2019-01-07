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
    private static $getIdCommand = 'ssh %s@%s "sudo docker node ls --filter label=%s --format \'{{.ID}}\'"';
    private static $removeCommand = 'ssh %s@%s "sudo docker node rm %s"';


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
     * @return mixed
     */
    public function startComputing(bool $returnInsteadOfCall = false)
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('startComputing'), $returnInsteadOfCall);
        if (\is_string($result) && !$returnInsteadOfCall) {
            return json_decode($result, true);
        }
        return $result;
    }


    /**
     * @param bool $returnInsteadOfCall
     * @return mixed
     */
    public function stopComputing(bool $returnInsteadOfCall = false)
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('stopComputing'), $returnInsteadOfCall);
        if (\is_string($result) && !$returnInsteadOfCall) {
            return json_decode($result, true);
        }
        return $result;
    }


    /**
     * @param bool $returnInsteadOfCall
     * @return null|string
     */
    public function getNodeId(bool $returnInsteadOfCall = false): ?string
    {
        return ServerUtility::executeShellCommand($this->parseCommand('getId', $this->instance->getId()), $returnInsteadOfCall);
    }

    /**
     * @param bool $returnInsteadOfCall
     * @return null|string
     */
    public function remove(bool $returnInsteadOfCall = false): ?string
    {
        return ServerUtility::executeShellCommand($this->parseCommand('remove', $this->getNodeId($returnInsteadOfCall)));
    }

    /**
     * @param bool $returnInsteadOfCall
     * @return mixed
     */
    public function inspect(bool $returnInsteadOfCall = false)
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('inspectCommand', $this->getNodeId($returnInsteadOfCall)), $returnInsteadOfCall);

        if (\is_string($result) && !$returnInsteadOfCall) {
            return json_decode($result, true);
        }
        return $result;
    }

    /**
     * @param string $commandName
     * @param string $nodeIdParam
     * @return string
     */
    protected function parseCommand(string $commandName, string $nodeIdParam = ''): string
    {
        if (!$nodeIdParam) {
            $nodeIdParam = $this->instance->getFqdn();
        }
        ServerUtility::validateParams([self::$username, $this->instance->getRunnerCoordinator(), $nodeIdParam]);

        $commandName .= 'Command';
        return sprintf(self::$$commandName, self::$username, $this->instance->getRunnerCoordinator(), $nodeIdParam);
    }
}