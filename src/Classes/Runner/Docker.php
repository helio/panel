<?php

namespace Helio\Panel\Runner;

use Helio\Panel\Job\DispatchConfig;
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
     * @return mixed
     */
    public function startComputing()
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('startComputing'));
        if (\is_string($result) && $result) {
            return json_decode($result, true);
        }
        return $result;
    }


    /**
     * @return mixed
     */
    public function stopComputing()
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('stopComputing'));
        if (\is_string($result) && $result) {
            return json_decode($result, true);
        }
        return $result;
    }


    /**
     * @return null|string
     */
    public function getNodeId(): ?string
    {
        return ServerUtility::executeShellCommand($this->parseCommand('getId', $this->instance->getId()));
    }

    /**
     * @return null|string
     */
    public function remove(): ?string
    {
        return ServerUtility::executeShellCommand($this->parseCommand('remove', $this->getNodeId()));
    }

    /**
     * @return mixed
     */
    public function inspect()
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('inspect', $this->getNodeId()));

        if (\is_string($result) && $result) {
            return json_decode($result, true);
        }
        return $result;
    }


    /**
     * @param DispatchConfig $config
     * @return string
     */
    public function createConfigForJob(DispatchConfig $config): string {
        $image = $config->getImage();
        $envVars = '';
        foreach ($config->getEnvVariables() as $name => $value) {
            $envVars .= "    - $name=$value\n";
        }
        return <<<EOC
version: '3'
services:
  app:
    image: "$image"
    environment:
$envVars
EOC;
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