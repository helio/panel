<?php

namespace Helio\Panel\Orchestrator;

use Helio\Panel\Model\Instance;
use Helio\Panel\Utility\ServerUtility;

class Choria implements OrchestratorInterface
{


    /**
     * @var Instance
     */
    protected $instance;

    /**
     * @var string
     */
    private static $username = 'panel';

    /**
     * @var string
     */
    private static $inventoryCommand = 'ssh %s@%s "mco inventory -F fqdn=/%s/ --script <(echo \\"
inventory do
  format \'{\\\\\\"identity\\\\\\": \\\\\\"%%s\\\\\\", \\\\\\"processor\\\\\\": \\\\\\"%%s\\\\\\", \\\\\\"kernelrelease\\\\\\": \\\\\\"%%s\\\\\\", \\\\\\"memorysize\\\\\\": \\\\\\"%%s\\\\\\"}\'
  fields { [ identity, facts[\'processorcount\'], facts[\'kernelrelease\'], facts[\'memorysize\'] ] }
end
\\")"';


    /**
     * Puppet constructor.
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
    public function getInventory(bool $returnInsteadOfCall = false)
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('inventory'), $returnInsteadOfCall);
        if (\is_string($result) && !$returnInsteadOfCall) {
            return json_decode($result, true);
        }
        return $result;
    }

    /**
     * @param string $commandName
     * @return string
     */
    protected function parseCommand(string $commandName): string
    {
        ServerUtility::validateParams([self::$username, $this->instance->getOrchestratorCoordinator(), $this->instance->getFqdn()]);

        $commandName .= 'Command';
        return sprintf(self::$$commandName, self::$username, $this->instance->getOrchestratorCoordinator(), str_replace('.', '\\\\.', 'infrastructure2.c.peppy-center-135409.internal'));//$this->instance->getFqdn()));
    }
}