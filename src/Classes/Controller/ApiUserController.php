<?php

namespace Helio\Panel\Controller;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Controller\Traits\ModelUserController;
use Helio\Panel\Controller\Traits\ModelParametrizedController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Instance;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\JwtUtility;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ApiController.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/user')
 */
class ApiUserController extends AbstractController
{
    use ModelUserController;
    use ModelParametrizedController;
    use TypeApiController;

    /**
     * @return ResponseInterface
     *
     * @Route("", methods={"GET"}, name="user.get")
     */
    public function getUserAction(): ResponseInterface
    {
        return $this->render([
            'name' => $this->user->getName(),
            'email' => $this->user->getEmail(),
        ]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/instancelist", methods={"GET"}, name="user.serverlist")
     *
     * @throws Exception
     */
    public function serverListAction(): ResponseInterface
    {
        $limit = (int) ($this->params['limit'] ?? 10);
        $offset = (int) ($this->params['offset'] ?? 0);
        $order = explode(' ', filter_var($this->params['orderby'] ?? 'created DESC', FILTER_SANITIZE_STRING));
        $orderBy = [$order[0] => $order[1]];

        $servers = [];
        foreach (App::getDbHelper()->getRepository(Instance::class)->findBy(['owner' => $this->user], $orderBy, $limit, $offset) as $instance) {
            /* @var Instance $instance */
            $servers[] = ['id' => $instance->getId(), 'html' => $this->fetchPartial('listItemInstance', ['instance' => $instance, 'user' => $this->user])];
        }

        return $this->render(['items' => $servers, 'user' => $this->user]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/joblist", methods={"GET"}, name="user.serverlist")
     *
     * @throws Exception
     */
    public function jobListAction(): ResponseInterface
    {
        $limit = (int) ($this->params['limit'] ?? 10);
        $offset = (int) ($this->params['offset'] ?? 0);
        $includeTerminated = (bool) ($this->params['deleted'] ?? 0);
        $returnHTML = (bool) ($this->params['html'] ?? true);
        $type = $this->params['type'] ?? null;
        $order = explode(' ', filter_var($this->params['orderby'] ?? 'created DESC', FILTER_SANITIZE_STRING));
        $orderBy = [$order[0] => $order[1]];
        $searchCriteria = ['owner' => $this->user];

        // if the user wants to see terminated workloads, skip the status filter
        if (!$includeTerminated) {
            $searchCriteria['status'] = JobStatus::getAllButDeletedAndUnknownStatusCodes();
        }
        if ($type) {
            $searchCriteria['type'] = $type;
        }

        $jobRepository = App::getDbHelper()->getRepository(Job::class);
        $jobs = $jobRepository->findBy($searchCriteria, $orderBy, $limit, $offset);

        $jobs = array_map(function (Job $job) use ($returnHTML) {
            $executions = $job->getExecutions();
            $totalExecutions = $executions->count();
            $openExecutions = 0;
            $runningExecutions = 0;
            $doneExecutions = 0;

            $executions = $executions->map(function (Execution $execution) use ($job, &$openExecutions, &$runningExecutions, &$doneExecutions) {
                $status = $execution->getStatus();
                if (ExecutionStatus::isRunning($status)) {
                    ++$runningExecutions;
                }
                if (ExecutionStatus::isValidPendingStatus($status)) {
                    ++$openExecutions;
                }
                if (ExecutionStatus::isNotRequiredToRunAnymore($status)) {
                    ++$doneExecutions;
                }

                return [
                    'id' => $execution->getId(),
                    'priority' => $execution->getPriority(),
                    'results' => $execution->getStats(),
                    'latestHeartbeat' => $execution->getLatestHeartbeat(),
                    'status' => $execution->getStatus(),
                    'finished' => ExecutionStatus::isNotRequiredToRunAnymore($execution->getStatus()),
                    'estimates' => JobFactory::getDispatchConfigOfJob($job, $execution)->getExecutionEstimates(),
                ];
            });

            $data = [
                'id' => $job->getId(),
                'name' => $job->getName(),
                'config' => $job->getConfig(),
                'billingReference' => $job->getBillingReference(),
                'budget' => $job->getBudget(),
                'type' => $job->getType(),
                'priority' => $job->getPriority(),
                'created' => $job->getCreated()->getTimestamp(),
                'autoExecSchedule' => $job->getAutoExecSchedule(),
                'location' => $job->getLocation(),
                'cpus' => $job->getCpus(),
                'gpus' => $job->getGpus(),
                'status' => $job->getStatus(),
                'executions' => $executions->toArray(),
                'total_executions' => $totalExecutions,
                'open_executions' => $openExecutions,
                'running_executions' => $runningExecutions,
                'done_executions' => $doneExecutions,
            ];
            if (!$returnHTML) {
                $data['html'] = $this->fetchPartial('listItemJob', [
                    'job' => $job,
                    'user' => $this->user,
                    'files' => array_filter(scandir(ExecUtility::getJobDataFolder($job), SCANDIR_SORT_ASCENDING), function (string $item) {
                        return 0 !== strpos($item, '.') && strpos($item, '.tar.gz') > 0;
                    }),
                ]);
            }

            return $data;
        }, $jobs);

        return $this->render(['items' => $jobs]);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/update", methods={"POST"}, name="user.update")
     */
    public function updateProfileAction(): ResponseInterface
    {
        $this->optionalParameterCheck(['username' => FILTER_SANITIZE_STRING,
            'role' => FILTER_SANITIZE_STRING,
            'email' => FILTER_SANITIZE_EMAIL,
        ]);

        if (array_key_exists('username', $this->params)) {
            $this->user->setName($this->params['username']);
        }
        if (array_key_exists('role', $this->params)) {
            $this->user->setRole($this->params['role']);
        }
        if (array_key_exists('email', $this->params)) {
            $this->user->setEmail($this->params['email']);
        }

        $config = json_decode($this->user->getConfig(), true);
        $validConfigOptions = ['gitlabtags', 'instancelevel', 'instancelocation'];
        foreach ($validConfigOptions as $option) {
            if (array_key_exists($option, $this->params)) {
                $this->optionalParameterCheck([$option => FILTER_SANITIZE_STRING]);
                $config[$option] = $this->params[$option];
            }
        }
        $this->user->setConfig(json_encode($config));

        App::getDbHelper()->flush($this->user);

        return $this->render(['message' => 'done']);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/settoken", methods={"PUT"}, name="user.settoken")
     */
    public function generateTokenAction(): ResponseInterface
    {
        $this->optionalParameterCheck(['eternal', FILTER_SANITIZE_STRING]);
        $duration = (array_key_exists('eternal', $this->params) && (bool) $this->params['eternal']) ? 'sticky' : null;
        $token = JwtUtility::generateToken($duration, $this->user)['token'];

        return $this->render(['message' => "<strong>Your Token is $token</strong> Save it in your Password manager, it cannot be displayed ever again."]);
    }
}
