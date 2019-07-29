<?php

namespace Helio\Panel\Controller;


use \Exception;
use Helio\Panel\Utility\JwtUtility;
use \RuntimeException;
use \DateTime;
use \DateInterval;
use Helio\Panel\App;
use Helio\Panel\Controller\Traits\AuthorizedAdminController;
use Helio\Panel\Controller\Traits\ModelInstanceController;
use Helio\Panel\Controller\Traits\ModelJobController;
use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Model\Instance;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class ApiController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 *
 * @RoutePrefix('/api/admin')
 *
 */
class ApiAdminController extends AbstractController
{
    use ModelInstanceController, ModelJobController {
        ModelInstanceController::setupParams insteadof ModelJobController;
        ModelInstanceController::requiredParameterCheck insteadof ModelJobController;
        ModelInstanceController::optionalParameterCheck insteadof ModelJobController;
    }
    use AuthorizedAdminController;
    use TypeDynamicController;


    /**
     * ApiAdminController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setMode('api');
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/instancelist", methods={"GET"}, name="user.serverlist")
     * @throws Exception
     */
    public function serverListAction(): ResponseInterface
    {
        $limit = (int)($this->params['limit'] ?? 10);
        $offset = (int)($this->params['offset'] ?? 0);
        $order = explode(',', filter_var($this->params['orderby'] ?? 'status DESC, priority ASC', FILTER_SANITIZE_STRING));
        $orderBy = [];
        foreach ($order as $field) {
            $field = explode(' ', trim($field));
            $orderBy[$field[0]] = $field[1];
        }

        $servers = [];
        foreach (App::getDbHelper()->getRepository(Instance::class)->findBy([], $orderBy, $limit, $offset) as $instance) {
            /** @var Instance $instance */
            $servers[] = ['id' => $instance->getId(), 'html' => $this->fetchPartial('listItemInstance', ['instance' => $instance, 'user' => $this->user, 'admin' => true])];
        }
        return $this->render(['items' => $servers, 'user' => $this->user]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/joblist", methods={"GET"}, name="admin.joblist")
     * @throws Exception
     */
    public function jobListAction(): ResponseInterface
    {
        $limit = (int)($this->params['limit'] ?? 10);
        $offset = (int)($this->params['offset'] ?? 0);
        $order = explode(',', filter_var($this->params['orderby'] ?? 'created DESC', FILTER_SANITIZE_STRING));
        $orderBy = [];
        foreach ($order as $field) {
            $field = explode(' ', trim($field));
            $orderBy[$field[0]] = $field[1];
        }

        $jobs = [];
        foreach (App::getDbHelper()->getRepository(Job::class)->findBy([], $orderBy, $limit, $offset) as $job) {
            /**@var Job $job */
            $jobs[] = ['id' => $job->getId(), 'html' => $this->fetchPartial('listItemJob', ['job' => $job, 'user' => $this->user, 'admin' => true])];
        }
        return $this->render(['items' => $jobs, 'user' => $this->user]);
    }


    /**
     * @return ResponseInterface
     * @throws Exception
     *
     * @Route("/update", methods={"POST"}, name="user.update")
     */
    public function updateProfileAction(): ResponseInterface
    {

        $this->optionalParameterCheck(['username' => FILTER_SANITIZE_STRING,
            'role' => FILTER_SANITIZE_STRING,
            'email' => FILTER_SANITIZE_EMAIL
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
     * @throws Exception
     */
    public function statsAction(): ResponseInterface
    {
        $now = new DateTime('now', ServerUtility::getTimezoneObject());
        $stale = $now->sub(new DateInterval('PT1H'));

        $avgWaitQuery = App::getDbHelper()->getRepository(Task::class)->createQueryBuilder('avg_wait');
        $avgWaitQuery->select('AVG(TIMESTAMPDIFF(SECOND, avg_wait.created, ' . $now->format('YmdHis') . ')) as avg')->where('avg_wait.status = ' . TaskStatus::READY);
        $staleQuery = App::getDbHelper()->getRepository(Task::class)->createQueryBuilder('stale');
        $staleQuery->select('COUNT(stale.id) as count')->where('stale.status = ' . TaskStatus::RUNNING)->andWhere('TIMESTAMPDIFF(SECOND, stale.latestAction, ' . $stale->format('YmdHis') . ') > 600');

        return $this->render([
            'active_jobs' => App::getDbHelper()->getRepository(Job::class)->count(['status' => JobStatus::READY]),
            'running_tasks' => App::getDbHelper()->getRepository(Task::class)->count(['status' => TaskStatus::RUNNING]),
            'waiting_tasks' => App::getDbHelper()->getRepository(Task::class)->count(['status' => TaskStatus::READY]),
            'done_tasks' => App::getDbHelper()->getRepository(Task::class)->count(['status' => TaskStatus::DONE]),
            'task_avg_wait' => $avgWaitQuery->getQuery()->getArrayResult()[0]['avg'],
            'stale_tasks' => $staleQuery->getQuery()->getArrayResult()[0]['count']
        ]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/redispatch", methods={"POST", "PUT", "GET"}, name="job.reexec")
     */
    public function execAction(): ResponseInterface
    {
        try {
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
                    'service_name' => $job->getType() . '-' . $job->getId()
                ]
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
        if (!$this->job || !JobType::isValidType($this->job->getType())) {
            return $this->render([], StatusCode::HTTP_NOT_ACCEPTABLE);
        }

        $services = [];
        /** @var Task $task */
        foreach ($this->job->getTasks() as $task) {
            // prepare and reset vars
            $serviceprefix = $this->job->getType() . '-' . $this->job->getId();
            $servicename = $serviceprefix . '-' . $task->getId();
            $services[$servicename]['service_name'] = $servicename;
            $taskEnv = [];
            $yamlEnv = [];
            $env = [];

            // catch done or not-yet-ready tasks
            if (TaskStatus::isNotRequiredToRunAnymore($task->getStatus())) {
                $services[$servicename]['ensure'] = 'absent';
                continue;
            }

            $dcfjt = JobFactory::getDispatchConfigOfJob($this->job, $task)->getDispatchConfig();
            if ($dcfjt->getEnvVariables()) {
                foreach ($dcfjt->getEnvVariables() as $key => $value) {
                    // it might be due to json array and object mixup, that value is still an array
                    if (is_array($value)) {
                        foreach ($value as $subkey => $subvalue) {
                            $env[$subkey] = $subvalue;
                        }
                    } else {
                        $env[$key] = $value;
                    }
                }
            }

            // merge Yaml Config
            if ($task->getConfig('env')) {
                foreach ($task->getConfig('env') as $key => $value) {
                    // it might be due to json array and object mixup, that value is still an array
                    if (is_array($value)) {
                        foreach ($value as $subkey => $subvalue) {
                            $taskEnv[$subkey] = $subvalue;
                        }
                    } else {
                        $taskEnv[$key] = $value;
                    }
                }
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $taskEnv = array_merge($env, $taskEnv);
            } else {
                $taskEnv = $env;
            }
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $taskEnv = array_merge(['HELIO_JOBID' => $this->job->getId(), 'HELIO_USERID' => $this->job->getOwner()->getId(), 'HELIO_TASKID' => $task->getId()], $taskEnv);
            // TODO: These redundante quotes are here to make env stuff `docker service create` compatible :(
            foreach ($taskEnv as $item => $value) {
                $yamlEnv[] = "'$item=$value'";
            }

            // compose service config
            $services[$servicename] = [
                'service_name' => $servicename,
                'image' => $dcfjt->getImage(),
                'replicas' => $dcfjt->getReplicaCountForJob($this->job),
                'env' => $yamlEnv,
            ];

            // set args if present
            $args = $task->getConfig('args') ?: $dcfjt->getArgs();
            if ($args) {
                $services[$servicename]['args'] = implode(' ', $args);
            }
        }

        // compose resulting yaml
        $result = [];

        $dcfj = JobFactory::getDispatchConfigOfJob($this->job)->getDispatchConfig();
        {
            if ($dcfj->getRegistry()) {
                $result['profile::docker::private_registry'] = $dcfj->getRegistry();
            }
        }
        $result['profile::docker::clusters'] = $services;

        return $this
            ->setReturnType('yaml')
            ->render($result);
    }
}
