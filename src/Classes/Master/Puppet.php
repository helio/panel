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
     * TODO: Set properly once KEHA is ready
     */
    protected static $dispatchCommand = 'ssh %s@%s "mco puppet runonce -W fqdn=%s"';


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
    public function getStatus(bool $returnInsteadOfCall = false)
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('status'), $returnInsteadOfCall);
        if (\is_string($result) && !$returnInsteadOfCall) {
            return json_decode($result, true);
        }
        return $result;
    }

    /**
     * @param bool $returnInsteadOfCall
     * @return mixed
     */
    public function doSign(bool $returnInsteadOfCall = false)
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('autosign'), $returnInsteadOfCall);
        if (\is_string($result) && !$returnInsteadOfCall) {
            return json_decode($result, true);
        }
        return $result;

    }

    /**
     * @param bool $returnInsteadOfCall
     * @return mixed
     */
    public function cleanup(bool $returnInsteadOfCall = false)
    {
        return ServerUtility::executeShellCommand($this->parseCommand('cleanup'), $returnInsteadOfCall);
    }


    /**
     * @param Job $job
     * @param bool $returnInsteadOfCall
     * @return bool
     */
    public function dispatchJob(Job $job, bool $returnInsteadOfCall = false): bool
    {
        return ServerUtility::executeShellCommand($this->parseCommand('dispatch'), $returnInsteadOfCall);
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