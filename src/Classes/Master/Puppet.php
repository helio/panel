<?php

namespace Helio\Panel\Master;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Server;
use Helio\Panel\Utility\ServerUtility;

class Puppet implements MasterInterface
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
    private static $autosignCommand = 'ssh %s@%s "autosign generate -b %s"';

    /**
     * @var string
     */
    private static $statusCommand = 'ssh %s@%s "curl -s -X GET https://puppetdb.idling.host/pdb/query/v4/nodes/%s -k"';

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
     * @return string
     */
    public function getStatus(bool $returnInsteadOfCall = false): string
    {
        return ServerUtility::executeShellCommand($this->parseCommand('status'), $returnInsteadOfCall);
    }

    /**
     * @param bool $returnInsteadOfCall
     * @return string
     */
    public function doSign(bool $returnInsteadOfCall = false): string
    {
        return ServerUtility::executeShellCommand($this->parseCommand('autosign'), $returnInsteadOfCall);

    }


    /**
     * @param string $commandName
     * @return string
     */
    protected function parseCommand(string $commandName): string
    {
        ServerUtility::validateParams([self::$username, $this->instance->getMasterCoordinator(), $this->instance->getFqdn()]);

        $commandName .= 'Command';
        return sprintf(self::$$commandName, self::$username, $this->instance->getMasterCoordinator(), $this->instance->getFqdn());
    }
}