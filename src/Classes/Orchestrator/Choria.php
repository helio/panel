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
  format \'{ \\\\\\"uptime\\\\\\": \\\\\\"%%s\\\\\\", \\\\\\"processor0\\\\\\": \\\\\\"%%s\\\\\\", \\\\\\"os\\\\\\": \\\\\\"%%s\\\\\\", \\\\\\"identity\\\\\\": \\\\\\"%%s\\\\\\", \\\\\\"processors\\\\\\": \\\\\\"%%s\\\\\\", \\\\\\"kernelrelease\\\\\\": \\\\\\"%%s\\\\\\", \\\\\\"memorysize\\\\\\": \\\\\\"%%s\\\\\\" }\'
  fields { [ facts[\'system_uptime\'][\'seconds\'], facts[\'processor0\'], facts[\'os\'][\'distro\'][\'description\'], identity, facts[\'processorcount\'], facts[\'kernelrelease\'], facts[\'memorysize\'] ] }
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
     * @return mixed
     */
    public function getInventory()
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('inventory'));
        if (\is_string($result) && $result) {
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
        return sprintf(self::$$commandName, self::$username, $this->instance->getOrchestratorCoordinator(), str_replace('.', '\\\\.', $this->instance->getFqdn()));
    }
}