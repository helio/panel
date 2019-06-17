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
     * @var int
     */
    private static $redundancyCount = 0;


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
     */
    protected static $redundantManagersCommand = 'ssh %s@%s "mco playbook run infrastructure::gce::create --input \'{\\"node\\":[\\"%s\\"],\\"master_token\\":\\"%s\\",\\"callback\\":\\"%s\\",\\"id\\":\\"%s\\",\\"token\\":\\"%s\\"}\'" 2>/dev/null >/dev/null &';


    /**
     * @var string
     */
    protected static $deleteInitManagerCommand = 'ssh %s@%s "mco playbook run infrastructure::gce::delete --input \'{\\"node\\":\\"%s\\",\\"callback\\":\\"%s\\",\\"id\\":\\"%s\\",\\"token\\":\\"%s\\"}\'" 2>/dev/null >/dev/null &';


    /**
     * @var string
     */
    protected static $deleteRedundantManagersCommand = 'ssh %s@%s "mco playbook run infrastructure::gce::delete --input \'{\\"node\\":[\\"%s\\"],\\"master_token\\":\\"%s\\",\\"callback\\":\\"%s\\",\\"id\\":\\"%s\\",\\"token\\":\\"%s\\"}\'" 2>/dev/null >/dev/null &';


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
     *
     */
    public function dispatchJob(Job $job): bool
    {
        if (!$job->getManagerNodes() || !$job->getClusterToken() || !$job->getInitManagerIp()) {
            LogHelper::warn('dispatchJob called on job that\'s not ready. Aborting.');
            return false;
        }

        $resultDispatch = ServerUtility::executeShellCommand($this->parseCommand('dispatch', [$job->getManagerNodes()[0]]));
        LogHelper::debug('response from choria at dispatchJob dispatch:' . print_r($resultDispatch, true));

        $resultJoinWorkers = ServerUtility::executeShellCommand($this->parseCommand('joinWorkers', [$job->getClusterToken(), $job->getInitManagerIp(), 1]));
        LogHelper::debug('response from choria at dispatchJob joinWorkers:' . print_r($resultJoinWorkers, true));

        return $resultDispatch && $resultJoinWorkers;
    }


    /**
     * @param Job $job
     * @return bool
     */
    public function provisionManager(Job $job): bool
    {
        $managerHash = ServerUtility::getShortHashOfString($job->getId());

        // we're good
        if (\count($job->getManagerNodes()) === (1 + self::$redundancyCount)) {
            return true;
        }

        // we have to provision the init manager
        if (\count($job->getManagerNodes()) === 0) {
            $managerHostname = self::$managerPrefix . "-init-${managerHash}";

            $command = 'firstManager';
            $params[] = $managerHostname;
        } else {

            // if no init manager exists, we cannot create redundancy managers
            if (!$job->getInitManagerIp()) {
                return false;
            }

            $redundancyNodes = [];

            $i = self::$redundancyCount + 1;
            while ($i <= self::$redundancyCount) {
                $redundancyNodes[] = self::$managerPrefix . "-redundancy-${managerHash}-${i}";
                $i--;
            }

            $command = 'redundantManagers';
            $params[] = implode('\\",\\"', $redundancyNodes);
            $params[] = $job->getManagerToken();
        }

        $params[] = ServerUtility::getBaseUrl() . 'api/job/callback?jobid=' . $job->getId() . '&token=' . $job->getOwner()->getToken();
        $params[] = $job->getId();
        $params[] = $job->getToken();

        $result = ServerUtility::executeShellCommand($this->parseCommand($command, $params));
        LogHelper::debug('response from choria at provision ' . $command . ':' . print_r($result, true));
        return $result;
    }


    /**
     * @param Job $job
     * @return bool
     */
    public function removeManager(Job $job): bool
    {
        $result = true;
        $managerHash = ServerUtility::getShortHashOfString($job->getId());

        // remove redundnat managers if existing
        if (\count($job->getManagerNodes()) > 1) {
            foreach ($job->getManagerNodes() as $node) {
                if (strpos($node, self::$managerPrefix . "-redundancy-${managerHash}") === 0) {
                    $result = $result && ServerUtility::executeShellCommand($this->parseCommand('deleteRedundantManagers', [
                        substr($node, 0, \strlen(self::$managerPrefix . "-init-${managerHash}-1")),
                        ServerUtility::getBaseUrl() . 'api/job/callback?jobid=' . $job->getId() . '&token=' . $job->getOwner()->getToken(),
                        $job->getId(),
                        $job->getToken(),
                        $job->getManagerToken()
                    ]));
                    LogHelper::debug('response from choria at deleteRedundantManagers:' . print_r($result, true));
                }
            }
        }

        // lastly, delete redundant manager
        $result = $result && ServerUtility::executeShellCommand($this->parseCommand('deleteInitManager', [
            self::$managerPrefix . "-init-${managerHash}",
            ServerUtility::getBaseUrl() . 'api/job/callback?jobid=' . $job->getId() . '&token=' . $job->getOwner()->getToken(),
            $job->getId(),
            $job->getToken()
        ]));
        LogHelper::debug('response from choria at deleteInitManager:' . print_r($result, true));
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
        //TODO: Re-add ServerUtility::validateParams($params);

        $commandName .= 'Command';
        return vsprintf(self::$$commandName, $params);
    }
}