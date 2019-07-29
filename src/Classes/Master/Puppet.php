<?php

namespace Helio\Panel\Master;

use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\Instance;
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
    protected static $username = 'panel';

    /**
     * @var string
     */
    protected static $autosignCommand = 'ssh %s@%s "autosign generate -b %s"';

    /**
     * @var string
     */
    protected static $statusCommand = 'ssh %s@%s "curl -s -X GET https://puppetdb.idling.host/pdb/query/v4/nodes/%s -k"';


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
    public function getStatus()
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('status'));
        LogHelper::debug('response from puppet at getStatus:' . print_r($result, true));
        if (\is_string($result) && strpos(trim($result), '{') === 0) {
            return json_decode($result, true);
        }
        return trim($result);
    }

    /**
     * @return mixed
     */
    public function doSign()
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('autosign'));
        LogHelper::debug('response from puppet at doSign:' . print_r($result, true));
        if (\is_string($result) && strpos(trim($result), '{') === 0) {
            return json_decode($result, true);
        }
        return trim($result);

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