<?php

namespace Helio\Panel\Orchestrator;

use Helio\Panel\App;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
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
    private static $managerPrefix = 'manager';


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
     * @ string
     */
    protected static $getInitManagerIpCommand = 'ssh %s@%s "mco inventory -F fqdn=/%s/ --script <(echo \\"
inventory do
  format \'%%s\'
  fields { [ facts[\'docker\'][\'Swarm\'][\'RemoteManagers\'][0][\'Addr\'] ] }
end
\\")"';


    /**
     * @var string
     */
    protected static $getDockerTokenCommand = 'ssh %s@%s "$(echo $(mco tasks run docker::swarm_token --node_role manager -I %s --background --summary | sed -r \\"s/\x1B\[([0-9]{1,2}(;[0-9]{1,2})?)?[mGK]//g\\" | grep -Eo \\"mco tasks status[^\']+\\") -F fqdn=/%s/ --verbose) | grep _output"';


    /**
     * @var string
     */
    protected static $firstManagerCommand = 'ssh %s@%s "mco playbook run infrastructure::gce::create --input \'{\\"node\\":\\"%s\\",\\"callback\\":\\"%s\\",\\"id\\":\\"%s\\",\\"token\\":\\"%s\\"}\'" 2>/dev/null >/dev/null &';


    /**
     * @var string
     *
     * TODO: implement token (,\\"master_token\\":\\"%s\\")
     */
    protected static $redundantManagersCommand = 'ssh %s@%s "mco playbook run infrastructure::gce::create --input \'{\\"node\\":[\\"%s\\"],\\"callback\\":\\"%s\\",\\"id\\":\\"%s\\",\\"token\\":\\"%s\\"}\'" 2>/dev/null >/dev/null &';


    /**
     * @var string
     */
    protected static $dispatchCommand = 'ssh %s@%s "mco playbook run helio::task::update --input \'{\\"cluster_address\\":\\"%s\\"}\'"';

    /**
     * @var string
     */
    protected static $joinWorkersCommand = 'ssh %s@%s "mco playbook run helio::queue --input \'{\\"cluster_join_token\\":\\"%s\\",\\"cluster_join_address\\":\\"%s\\",\\"cluster_join_count\\":\\"%s\\"}\'"';


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
        $result = ServerUtility::executeShellCommand($this->parseCommand('inventory', [str_replace('.', '\\\\.', $this->instance->getFqdn())]));
        LogHelper::debug('response from choria at getInventory:' . print_r($result, true));
        if (\is_string($result) && $result) {
            return json_decode($result, true);
        }
        return $result;
    }


    /**
     * @param Job $job
     * @return bool
     */
    public function setInitManagerNodeIp(Job $job): bool
    {
        if (\count($job->getManagerNodes()) !== 1) {
            return false;
        }

        // todo: filter input (it's IP And a Port, thus FILTER_VALIDATE_IP won't work).
        $result = trim(ServerUtility::executeShellCommand($this->parseCommand('getInitManagerIp', [$job->getManagerNodes()[0]])));
        LogHelper::debug('response from choria at setInitManagerNodeIp:' . print_r($result, true));
        if (!$result) {
            return false;
        }
        $job->setInitManagerIp($result);

        try {
            App::getApp()->getContainer()->get('dbHelper')->persist($job);
            App::getApp()->getContainer()->get('dbHelper')->flush($job);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }


    /**
     * @param Job $job
     * @return bool
     *
     * @deprecated
     */
    public function setClusterToken(Job $job): bool
    {
        $result = filter_var(trim(ServerUtility::executeShellCommand($this->parseCommand('getDockerToken', [$job->getManagerNodes()[0], $job->getManagerNodes()[0]]))), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        LogHelper::debug('response from choria at setClusterToken:' . print_r($result, true));
        if (!$result) {
            return false;
        }
        $matches = [];
        preg_match('/"_output":"([\-A-Za-z0-9]+)/', $result, $matches);
        if (\count($matches) === 0) {
            return false;
        }
        $job->setClusterToken($matches[1]);

        try {
            App::getApp()->getContainer()->get('dbHelper')->persist($job);
            App::getApp()->getContainer()->get('dbHelper')->flush($job);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }


    /**
     * @param Job $job
     * @return bool
     *
     * TODO: verify if the joinWorkers call here is necessary or could be solved more elegantly.
     */
    public function dispatchJob(Job $job): bool
    {
        $result = ServerUtility::executeShellCommand($this->parseCommand('dispatch', [$job->getManagerNodes()[0]])) && $this->joinWorkers($job);
        LogHelper::debug('response from choria at dispatchJob:' . print_r($result, true));
        return $result;
    }


    /**
     * @param Job $job
     * @return bool
     */
    public function joinWorkers(Job $job): bool
    {

        $result = ServerUtility::executeShellCommand($this->parseCommand('joinWorkers', [$job->getClusterToken(), $job->getInitManagerIp(), 1]));
        LogHelper::debug('response from choria at provision joinWorkers:' . print_r($result, true));
        return true;
    }


    /**
     * @param Job $job
     * @return bool
     */
    public function provisionManager(Job $job): bool
    {
        $managerHash = ServerUtility::getShortHashOfString($job->getId());
        $command = '';

        if (\count($job->getManagerNodes()) === 3) {
            return true;
        }
        // only one manager node: need to provision two more
        if (\count($job->getManagerNodes()) === 1) {

            // check if manager node is already properly set up
            if (!$job->getInitManagerIp()) {
                return false;
            }
            $firstRedundantFqdn = self::$managerPrefix . "-redundancy-${managerHash}-1";
            $secondRedundantFqdn = self::$managerPrefix . "-redundancy-${managerHash}-2";

            $command = 'redundantManagers';
            $params[] = $firstRedundantFqdn . '\\",\\"' . $secondRedundantFqdn;
            // TODO: implement token
            //$params[] = $job->getManagerToken();
        }

        // No manager node initialized yet
        if (\count($job->getManagerNodes()) === 0) {
            $managerFqdn = self::$managerPrefix . "-init-${managerHash}";

            $command = 'firstManager';
            $params[] = $managerFqdn;
        }


        try {
            App::getApp()->getContainer()->get('dbHelper')->persist($job);
            App::getApp()->getContainer()->get('dbHelper')->flush($job);
            $params[] = ServerUtility::getBaseUrl() . 'api/job/callback?jobid=' . $job->getId() . '&token=' . $job->getOwner()->getToken();
            $params[] = $job->getId();
            $params[] = $job->getToken();
        } catch (\Exception $e) {
            return false;
        }

        if (!$command) {
            LogHelper::warn('empty command in Choria manager provisioning. this is only valid, if managers are already properly provisioned.');
            return false;
        }
        $result = ServerUtility::executeShellCommand($this->parseCommand($command, $params));
        LogHelper::debug('response from choria at provision ' . $command . ':' . print_r($result, true));
        return $result;
    }


    /**
     * @param string $commandName
     * @param array $parameter
     * @return string
     */
    protected function parseCommand(string $commandName, array $parameter = []): string
    {
        $params = array_merge([
            self::$username, $this->instance->getOrchestratorCoordinator()
        ],
            $parameter
        );
        //ServerUtility::validateParams($params);

        $commandName .= 'Command';
        return vsprintf(self::$$commandName, $params);
    }
}