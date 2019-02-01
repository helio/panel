<?php

namespace Helio\Panel\Master;

use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
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
     * @var string
     */
    protected static $cleanupCommand = 'ssh %s@%s "sudo /opt/puppetlabs/bin/puppetserver ca cert clean --certname %s"';

    /**
     * @var string
     *
     */
    protected static $dispatchCommand = 'ssh %s@%s "mco tasks run docker::jobs -I manager1.c.peppy-center-135409.internal"';


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
        if (\is_string($result) && $result) {
            return json_decode($result, true);
        }
        return $result;
    }

    /**
     * @return mixed
     */
    public function doSign()
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('autosign'));
        if (\is_string($result) && $result) {
            return json_decode($result, true);
        }
        return $result;

    }

    /**
     * @return mixed
     */
    public function cleanup()
    {
        return ServerUtility::executeShellCommand($this->parseCommand('cleanup'));
    }


    /**
     * @param Job $job
     * @return bool
     */
    public function dispatchJob(Job $job): bool
    {
        return ServerUtility::executeShellCommand($this->parseCommand('dispatch'));
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