<?php

namespace Helio\Panel\Controller;

use Exception;
use Helio\Panel\Controller\Traits\ModelInstanceController;
use Helio\Panel\Controller\Traits\ModelJobController;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Utility\JwtUtility;
use RuntimeException;
use DateTime;
use DateInterval;
use Helio\Panel\App;
use Helio\Panel\Controller\Traits\AuthorizedAdminController;
use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Model\Instance;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class ApiController.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 *
 * @RoutePrefix('/api/admin')
 */
class ApiAdminController extends AbstractController
{
    use AuthorizedAdminController, ModelInstanceController, ModelJobController {
        AuthorizedAdminController::setupUser insteadof ModelInstanceController, ModelJobController;
        AuthorizedAdminController::validateUserIsSet insteadof ModelInstanceController, ModelJobController;
        AuthorizedAdminController::persistUser insteadof ModelInstanceController, ModelJobController;

        ModelInstanceController::setupParams insteadof ModelJobController;
        ModelInstanceController::requiredParameterCheck insteadof ModelJobController;
        ModelInstanceController::optionalParameterCheck insteadof ModelJobController;
    }
    use TypeDynamicController;

    /**
     * ApiAdminController constructor.
     */
    public function __construct()
    {
        $this->jobIdCSVAllowed = true;
        $this->setMode('api');
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
        $order = explode(',', filter_var($this->params['orderby'] ?? 'status DESC, priority ASC', FILTER_SANITIZE_STRING));
        $orderBy = [];
        foreach ($order as $field) {
            $field = explode(' ', trim($field));
            $orderBy[$field[0]] = $field[1];
        }

        $servers = [];
        foreach (App::getDbHelper()->getRepository(Instance::class)->findBy([], $orderBy, $limit, $offset) as $instance) {
            /* @var Instance $instance */
            $servers[] = ['id' => $instance->getId(), 'html' => $this->fetchPartial('listItemInstance', ['instance' => $instance, 'user' => $this->user, 'admin' => true])];
        }

        return $this->render(['items' => $servers, 'user' => $this->user]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/joblist", methods={"GET"}, name="admin.joblist")
     *
     * @throws Exception
     */
    public function jobListAction(): ResponseInterface
    {
        $limit = (int) ($this->params['limit'] ?? 10);
        $offset = (int) ($this->params['offset'] ?? 0);
        $order = explode(',', filter_var($this->params['orderby'] ?? 'created DESC', FILTER_SANITIZE_STRING));
        $orderBy = [];
        foreach ($order as $field) {
            $field = explode(' ', trim($field));
            $orderBy[$field[0]] = $field[1];
        }

        $jobs = [];
        foreach (App::getDbHelper()->getRepository(Job::class)->findBy([], $orderBy, $limit, $offset) as $job) {
            /* @var Job $job */
            $jobs[] = ['id' => $job->getId(), 'html' => $this->fetchPartial('listItemJob', ['job' => $job, 'user' => $this->user, 'admin' => true])];
        }

        return $this->render(['items' => $jobs, 'user' => $this->user]);
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
        $this->optionalParameterCheck([
            'username' => FILTER_SANITIZE_STRING,
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
        App::getDbHelper()->flush($this->user);

        return $this->render();
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/stat", methods={"GET"}, name="exec.stats")
     *
     * @throws Exception
     */
    public function statsAction(): ResponseInterface
    {
        $now = new DateTime('now', ServerUtility::getTimezoneObject());
        $stale = $now->sub(new DateInterval('PT1H'));

        $avgWaitQuery = App::getDbHelper()->getRepository(Execution::class)->createQueryBuilder('avg_wait');
        $avgWaitQuery->select('AVG(TIMESTAMPDIFF(SECOND, avg_wait.created, ' . $now->format('YmdHis') . ')) as avg')->where('avg_wait.status = ' . ExecutionStatus::READY);
        $staleQuery = App::getDbHelper()->getRepository(Execution::class)->createQueryBuilder('stale');
        $staleQuery->select('COUNT(stale.id) as count')->where('stale.status = ' . ExecutionStatus::RUNNING)->andWhere('TIMESTAMPDIFF(SECOND, stale.latestAction, ' . $stale->format('YmdHis') . ') > 600');

        return $this->render([
            'active_jobs' => App::getDbHelper()->getRepository(Job::class)->count(['status' => JobStatus::READY]),
            'running_executions' => App::getDbHelper()->getRepository(Execution::class)->count(['status' => ExecutionStatus::RUNNING]),
            'waiting_executions' => App::getDbHelper()->getRepository(Execution::class)->count(['status' => ExecutionStatus::READY]),
            'done_executions' => App::getDbHelper()->getRepository(Execution::class)->count(['status' => ExecutionStatus::DONE]),
            'execution_avg_wait' => $avgWaitQuery->getQuery()->getArrayResult()[0]['avg'],
            'stale_executions' => $staleQuery->getQuery()->getArrayResult()[0]['count'],
        ]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/redispatch", methods={"POST", "PUT", "GET"}, name="admin.job.redispatch")
     */
    public function execAction(): ResponseInterface
    {
        try {
            if (!$this->job) {
                return $this->render(['success' => false, 'message' => 'job not found'], StatusCode::HTTP_NOT_FOUND);
            }
            if (!JobStatus::isValidActiveStatus($this->job->getStatus())) {
                throw new RuntimeException('job not ready');
            }
            OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->dispatchJob();
            $this->persistJob();

            return $this->render(['status' => 'success']);
        } catch (Exception $e) {
            return $this->render(['status' => 'error', 'reason' => $e->getMessage()], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/getjobtoken", methods={"GET"}, name="admin.getjobtoken")
     */
    public function getJobTokenAction(): ResponseInterface
    {
        if (!$this->job) {
            return $this->render(['success' => false, 'message' => 'job not found'], StatusCode::HTTP_NOT_FOUND);
        }
        $token = JwtUtility::generateToken(null, null, null, $this->job)['token'];

        return $this->render(['message' => "<strong>Your Token is $token</strong> Safe it in your Password manager, it cannot be displayed ever again."]);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/getinstancetoken", methods={"GET"}, name="admin.getinstancetoken")
     */
    public function getInstanceTokenAction(): ResponseInterface
    {
        if (!$this->instance) {
            return $this->render(['success' => false, 'message' => 'instance not found'], StatusCode::HTTP_NOT_FOUND);
        }
        $token = JwtUtility::generateToken(null, null, $this->instance)['token'];

        return $this->render(['message' => "<strong>Your Token is $token</strong> Safe it in your Password manager, it cannot be displayed ever again."]);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/getRunnersHiera", methods={"GET"}, name="admin.getJobHiera")
     */
    public function openJobsListForPuppetAction(): ResponseInterface
    {
        $jobs = App::getDbHelper()->getRepository(Job::class)->findBy(['status' => JobStatus::READY]);

        $jobList = [];
        $counter = 0;
        /** @var Job $job */
        foreach ($jobs as $job) {
            $jobList[] = [
                'job_number' => ++$counter,
                'job_specs' => [
                    'job_id' => $job->getId(),
                    'service_name' => $job->getType() . '-' . $job->getId(),
                ],
            ];
        }

        return $this
            ->setReturnType('yaml')
            ->render(['name' => 'profile::docker::backlog', 'jobs' => $jobList]);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/getJobHiera", methods={"GET"}, name="admin.getJobHiera")
     */
    public function jobConfigForPuppetAction(): ResponseInterface
    {
        if (!$this->job && !count($this->jobs)) {
            return $this->render(['success' => false, 'message' => 'job not found'], StatusCode::HTTP_NOT_FOUND);
        }
        $jobs = $this->jobs;
        if ($this->job) {
            $jobs = [$this->job];
        }

        $registry = null;

        $services = [];
        foreach ($jobs as $job) {
            /** @var Execution $execution */
            foreach ($job->getExecutions() as $execution) {
                $service = $this->generateExecutionHiera($job, $execution);
                $services[$service['service_name']] = $service;
            }

            // set job registry
            // this is slightly ugly now that we can have executions/services of different jobs. In theory each job
            // could have a different registry. If that's the case we'll error out.
            $dispatchConfig = JobFactory::getDispatchConfigOfJob($job)->getDispatchConfig();
            $jobRegistry = $dispatchConfig->getRegistry();

            if ($registry && $registry !== $jobRegistry) {
                LogHelper::err('registry different for list of supplied jobs', [
                    'registry' => $registry,
                    'jobRegistry' => $jobRegistry,
                    'jobid' => $this->request->getParams(['id', 'jobid']),
                ]);

                return $this->render(['error' => 'different registry of supplied jobs'], StatusCode::HTTP_BAD_REQUEST);
            } elseif ($jobRegistry) {
                $registry = $jobRegistry;
            }
        }

        if ($registry) {
            $result['profile::docker::private_registry'] = $registry;
        }

        $result['profile::docker::clusters'] = $services;

        return $this
            ->setReturnType('yaml')
            ->render($result);
    }

    private function generateExecutionHiera(Job $job, Execution $execution): array
    {
        $servicePrefix = $job->getType() . '-' . $job->getId();
        $serviceName = $servicePrefix . '-' . $execution->getId();

        $service = [
            'service_name' => $serviceName,
        ];

        $yamlEnv = [];
        $env = [];

        // catch done or not-yet-ready executions
        if (ExecutionStatus::isNotRequiredToRunAnymore($execution->getStatus())) {
            $service['ensure'] = 'absent';

            return $service;
        }

        $dispatchConfig = JobFactory::getDispatchConfigOfJob($job, $execution)->getDispatchConfig();
        if ($dispatchConfig->getEnvVariables()) {
            foreach ($dispatchConfig->getEnvVariables() as $key => $value) {
                // it might be due to json array and object mixup, that value is still an array
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $env[strtoupper($subKey)] = $subValue;
                    }
                } else {
                    $env[strtoupper($key)] = $value;
                }
            }
        }

        // merge Yaml Config
        if ($execution->getConfig('env')) {
            foreach ($execution->getConfig('env') as $key => $value) {
                // it might be due to json array and object mixup, that value is still an array
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $env[strtoupper($subKey)] = $subValue;
                    }
                } else {
                    $env[strtoupper($key)] = $value;
                }
            }
        }

        $env['HELIO_JOBID'] = $job->getId();
        $env['HELIO_USERID'] = $job->getOwner()->getId();
        $env['HELIO_EXECUTIONID'] = $execution->getId();

        foreach ($env as $item => $value) {
            // remove newlines because they cause yaml to parse them in a herein unwanted way
            $escapedVal = str_replace(["\n", "\r"], ['\n', '\r'], $value);
            $yamlEnv[] = escapeshellarg("$item=$escapedVal");
        }

        // set args if present
        $args = $execution->getConfig('args') ?: $dispatchConfig->getArgs();
        if ($args) {
            $service['args'] = implode(' ', $args);
        }

        $service['image'] = $dispatchConfig->getImage() ?: 'hello-world';
        $service['replicas'] = $dispatchConfig->getReplicaCountForJob($job);
        $service['env'] = $yamlEnv;

        return $service;
    }
}
