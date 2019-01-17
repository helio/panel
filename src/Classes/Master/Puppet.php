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
     * @var string
     */
    private static $cleanupCommand = 'ssh %s@%s "sudo /opt/puppetlabs/bin/puppetserver ca cert clean --certname %s"';

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
     * @param Instance $instance
     * @param Job $job
     * @return bool
     *
     * TODO: Implement
     */
    public function dispatchJob(Instance $instance, Job $job): bool {
        return true;
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