<?php

namespace Helio\Panel\Controller;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Helio\Panel\Exception\HttpException;
use Helio\Panel\Helper\ElasticHelper;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Manager;
use Helio\Panel\Orchestrator\ManagerStatus;
use Helio\Panel\Request\Log;
use Helio\Panel\Service\LogService;
use OpenApi\Annotations as OA;
use Helio\Panel\App;
use Helio\Panel\Controller\Traits\ModelInstanceController;
use Helio\Panel\Controller\Traits\TypeDynamicController;
use Helio\Panel\Controller\Traits\AuthorizedJobController;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Job\JobType;
use Helio\Panel\Model\Execution;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Utility\JwtUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class ApiController.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/job')
 *
 * @OA\Tag(name="job", description="Job related APIs")
 */
class ApiJobController extends AbstractController
{
    use AuthorizedJobController, ModelInstanceController {
        AuthorizedJobController::setupUser insteadof ModelInstanceController;
        AuthorizedJobController::validateUserIsSet insteadof ModelInstanceController;
        AuthorizedJobController::persistUser insteadof ModelInstanceController;

        AuthorizedJobController::setupParams insteadof ModelInstanceController;
        AuthorizedJobController::requiredParameterCheck insteadof ModelInstanceController;
        AuthorizedJobController::optionalParameterCheck insteadof ModelInstanceController;
    }

    use TypeDynamicController;

    /**
     * @var LogService
     */
    private $logService;

    public function __construct()
    {
        $this->logService = new LogService(ElasticHelper::getInstance());
    }

    /**
     * @return string
     */
    protected function getIdAlias(): string
    {
        return 'jobid';
    }

    /**
     * @OA\Post(
     *     path="/job",
     *     description="Create or update a job",
     *     tags={"job"},
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/Job")),
     *     @OA\Response(response="406", ref="#/components/responses/406"),
     *     @OA\Response(response="403", description="Max limit of running jobs reached"),
     *     @OA\Response(
     *         response="200",
     *         description="Job successfully created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="token",
     *                 type="string",
     *                 description="The authentication token that's only valid for this job"
     *             ),
     *             @OA\Property(
     *                 property="id",
     *                 type="number",
     *                 description="The id of the newly created job"
     *             ),
     *             @OA\Property(
     *                 property="success",
     *                 ref="#/components/schemas/default-content/properties/success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 ref="#/components/schemas/default-content/properties/message"
     *             ),
     *             @OA\Property(
     *                 property="notification",
     *                 ref="#/components/schemas/default-content/properties/notification"
     *             )
     *         )
     *     )
     * )
     *
     * @return ResponseInterface
     *
     * @Route("", methods={"POST"}, name="job.add")
     *
     * @throws Exception
     * @throws GuzzleException
     */
    public function addJobAction(): ResponseInterface
    {
        $runningJobsCount = $this->user->getRunningJobsCount();
        $runningJobsLimit = $this->user->getPreferences()->getLimits()->getRunningJobs();
        $jobTypeRestriction = $this->user->getPreferences()->getLimits()->getJobTypes();
        $managerNodeRestriction = $this->user->getPreferences()->getLimits()->getManagerNodes();

        if ($runningJobsCount >= $runningJobsLimit) {
            App::getNotificationUtility()::alertAdmin(sprintf('Running jobs limit (running: %d / limit: %d) reached for user %d / %d', $runningJobsCount, $runningJobsLimit, $this->user->getId(), $this->user->getEmail()));

            return $this->render([
                'success' => false,
                'message' => sprintf('Limit of running jobs reached. Amount running: %d / Limit: %d. Please contact helio support if you have any questions.', $runningJobsCount, $runningJobsLimit),
                'limits' => $this->user->getPreferences()->getLimits(),
            ], StatusCode::HTTP_FORBIDDEN);
        }

        // TODO: Remove this again once CPUs is implemented
        if (is_array($this->request->getParsedBody()) && array_key_exists('cpus', $this->request->getParsedBody())) {
            App::getNotificationUtility()::alertAdmin('Job with specified CPUs created by ' . $this->user->getId() . ' -> ' . $this->user->getEmail());
        }

        if (!$this->job->getType()) {
            $this->requiredParameterCheck([
                'type' => FILTER_SANITIZE_STRING,
            ]);

            if (!JobType::isValidType($this->params['type'])) {
                return $this->render(['success' => false, 'message' => 'Unknown Job Type'], StatusCode::HTTP_NOT_ACCEPTABLE);
            }

            $this->job->setType($this->params['type']);
        }

        // check if user is allowed to create job type
        if ($jobTypeRestriction && !in_array($this->job->getType(), $jobTypeRestriction, true)) {
            App::getNotificationUtility()::alertAdmin(sprintf('Job type restriction on type %s hit by user %d / %d', $this->job->getType(), $this->user->getId(), $this->user->getEmail()));

            return $this->render([
                'success' => false,
                'message' => 'Job Type not allowed for user',
            ], StatusCode::HTTP_NOT_ACCEPTABLE);
        }

        $this->optionalParameterCheck([
            'name' => FILTER_SANITIZE_STRING,
            'location' => FILTER_SANITIZE_STRING,
            'billingReference' => FILTER_SANITIZE_STRING,
            'config' => FILTER_SANITIZE_STRING,
            'autoExecSchedule' => FILTER_SANITIZE_STRING,
        ]);

        $this->job->setOwner($this->user);
        $isNew = null === $this->job->getId();
        JobFactory::getInstanceOfJob($this->job)->create($this->params);

        if (!$this->user->getPreferences()->getNotifications()->isMuteAdmin()) {
            $str = $isNew ? 'New Job was created' : 'Job was updated';
            App::getNotificationUtility()::notifyAdmin($str . ' by ' . $this->user->getEmail() . ', type: ' . $this->job->getType() . ', id: ' . $this->job->getId() . ', expected manager: manager-init-' . ServerUtility::getShortHashOfString($this->job->getId()));
        }

        if ($managerNodeRestriction) {
            $managers = App::getDbHelper()->getRepository(Manager::class)->findBy(['fqdn' => $managerNodeRestriction]);
            /** @var Manager $manager */
            foreach ($managers as $manager) {
                if ($manager->works()) {
                    $this->job->setManager($manager);
                    break;
                }
            }

            if (!$this->job->getManager()) {
                $this->job->setStatus(JobStatus::INIT_ERROR);
                $this->persistJob();

                return $this->render([
                    'success' => false,
                    'message' => 'Tried to create a job without available manager. This either means that you have no permission or that no usable manager exists at all.',
                ], StatusCode::HTTP_NOT_ACCEPTABLE);
            }

            $this->setJobReady($this->job, $this->job->getManager()->getName());

            $this->persistJob();
        } else {
            $managerName = OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->provisionManager();
            if ($this->job->getManager()) {
                $this->job->getManager()->setName($managerName);
            }
        }

        return $this->render([
            'success' => true,
            'token' => JwtUtility::generateToken(null, $this->user, null, $this->job)['token'],
            'id' => $this->job->getId(),
            'html' => $this->fetchPartial('listItemJob', ['job' => $this->job, 'user' => $this->user]),
            'message' => 'Job <strong>' . $this->job->getName() . '</strong> ' . ($isNew ? 'added' : 'updated'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/job",
     *     tags={"job"},
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Id of the job to delete",
     *         required=true,
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Job has been deleted",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="removed",
     *                 type="boolean",
     *                 description="Indicates whether the job has been deleted or cleanup still needs to be processed."
     *             ),
     *             @OA\Property(
     *                 property="success",
     *                 ref="#/components/schemas/default-content/properties/success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 ref="#/components/schemas/default-content/properties/message"
     *             ),
     *             @OA\Property(
     *                 property="notification",
     *                 ref="#/components/schemas/default-content/properties/notification"
     *             )
     *         )
     *     )
     * )
     *
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("", methods={"DELETE"}, name="job.remove")
     */
    public function removeJobAction(): ResponseInterface
    {
        if (null === $this->job->getId()) {
            return $this->render(['success' => false, 'message' => 'Job not found'], StatusCode::HTTP_NOT_FOUND);
        }

        if (!JobType::isValidType($this->job->getType())) {
            $this->job->setHidden(true)->setStatus(JobStatus::DELETED);
        } else {
            /* @var Execution $execution */
            JobFactory::getInstanceOfJob($this->job)->stop($this->params);

            // first: set all services to absent. then, remove the managers
            OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->dispatchJob();
            OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->removeManager();

            $this->job->setStatus(JobStatus::DELETING);
        }
        $this->persistJob();

        return $this->render(['success' => true, 'message' => 'Job scheduled for removal.', 'removed' => JobStatus::DELETED === $this->job->getStatus()]);
    }

    /**
     * @OA\Get(
     *     path="/job",
     *     tags={"job"},
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     description="Retrieve details of an existing job",
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Id of the job which status you want to see",
     *         required=true,
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="id",
     *                 ref="#/components/schemas/Job/properties/id"
     *             ),
     *             @OA\Property(
     *                 property="name",
     *                 ref="#/components/schemas/Job/properties/name"
     *             ),
     *             @OA\Property(
     *                 property="billingReference",
     *                 ref="#/components/schemas/Job/properties/billingReference"
     *             ),
     *             @OA\Property(
     *                 property="budget",
     *                 ref="#/components/schemas/Job/properties/budget"
     *             ),
     *             @OA\Property(
     *                 property="type",
     *                 ref="#/components/schemas/Job/properties/type"
     *             ),
     *             @OA\Property(
     *                 property="priority",
     *                 ref="#/components/schemas/Job/properties/priority"
     *             ),
     *             @OA\Property(
     *                 property="created",
     *                 type="string",
     *                 description="Creation date time"
     *             ),
     *             @OA\Property(
     *                 property="autoExecSchedule",
     *                 ref="#/components/schemas/Job/properties/autoExecSchedule"
     *             ),
     *             @OA\Property(
     *                 property="location",
     *                 ref="#/components/schemas/Job/properties/location"
     *             ),
     *             @OA\Property(
     *                 property="cpus",
     *                 ref="#/components/schemas/Job/properties/cpus"
     *             ),
     *             @OA\Property(
     *                 property="gpus",
     *                 ref="#/components/schemas/Job/properties/gpus"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="number",
     *                 description="Job status as a number"
     *             ),
     *             @OA\Property(
     *                 property="executions",
     *                 type="object",
     *                 @OA\Property(
     *                     property="newestRunTime",
     *                     description="Latest time this job has been started"
     *                 ),
     *                 @OA\Property(
     *                     property="total",
     *                     description="Total executions"
     *                 ),
     *                 @OA\Property(
     *                     property="pending",
     *                     description="Pending amount of executions"
     *                 ),
     *                 @OA\Property(
     *                     property="running",
     *                     description="Currently running executions"
     *                 ),
     *                 @OA\Property(
     *                     property="done",
     *                     description="Finished executions"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="success",
     *                 ref="#/components/schemas/default-content/properties/success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 ref="#/components/schemas/default-content/properties/message"
     *             ),
     *             @OA\Property(
     *                 property="notification",
     *                 ref="#/components/schemas/default-content/properties/notification"
     *             )
     *         )
     *     )
     * )
     *
     * @return ResponseInterface
     *
     * @Route("", methods={"GET"}, name="exec.job.status")
     */
    public function jobStatusAction(): ResponseInterface
    {
        if (null === $this->job->getId()) {
            return $this->render(['success' => false, 'message' => 'Job not found'], StatusCode::HTTP_NOT_FOUND);
        }

        /** @var Execution $newestRun */
        $newestRun = App::getDbHelper()->getRepository(Execution::class)->findOneBy(['job' => $this->job, 'status' => ExecutionStatus::DONE], ['created' => 'DESC']);

        return $this->render([
            'success' => true,
            'name' => $this->job->getName(),
            'id' => $this->job->getId(),
            'billingReference' => $this->job->getBillingReference(),
            'budget' => $this->job->getBudget(),
            'type' => $this->job->getType(),
            'priority' => $this->job->getPriority(),
            'created' => $this->job->getCreated()->getTimestamp(),
            'autoExecSchedule' => $this->job->getAutoExecSchedule(),
            'location' => $this->job->getLocation(),
            'cpus' => $this->job->getCpus(),
            'gpus' => $this->job->getGpus(),
            'status' => $this->job->getStatus(),
            'message' => 'Job Status is ' . JobStatus::getLabel($this->job->getStatus()),
            'executions' => [
                'newestRunTime' => $newestRun ? $newestRun->getCreated()->getTimestamp() : 0,
                'total' => App::getDbHelper()->getRepository(Execution::class)->count(['job' => $this->job]),
                'pending' => App::getDbHelper()->getRepository(Execution::class)->count(['job' => $this->job, 'status' => ExecutionStatus::READY]),
                'running' => App::getDbHelper()->getRepository(Execution::class)->count(['job' => $this->job, 'status' => ExecutionStatus::RUNNING]),
                'done' => App::getDbHelper()->getRepository(Execution::class)->count(['job' => $this->job, 'status' => ExecutionStatus::DONE]),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/job/isready",
     *     tags={"job"},
     *     description="HTTP Status Code indicates if the job is properly provisioned and ready to be executed",
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Id of the job which status you want to see",
     *         required=true,
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(response="200",
     *     description="Job is ready and can be executed",
     *         @OA\JsonContent(
     *             @OA\Schema(ref="#/components/schemas/default-content")
     *         )
     *     ),
     *     @OA\Response(response="424",
     *     description="Job is not ready",
     *         @OA\JsonContent(
     *             @OA\Schema(ref="#/components/schemas/default-content")
     *         )
     *     )
     * )
     *
     * @return ResponseInterface
     *
     * @Route("/isready", methods={"GET"}, name="exec.job.status")
     */
    public function jobIsReadyAction(): ResponseInterface
    {
        if (null === $this->job->getId()) {
            return $this->render(['success' => false, 'message' => 'Job not found'], StatusCode::HTTP_NOT_FOUND);
        }
        $ready = JobStatus::READY === $this->job->getStatus();
        $status = $ready ? StatusCode::HTTP_OK : StatusCode::HTTP_FAILED_DEPENDENCY;
        $message = $ready ? 'Job is ready' : 'Execution environment for job is being prepared...';

        return $this->render(['success' => true, 'message' => $message], $status);
    }

    /**
     * @OA\Get(
     *     path="/job/isdone",
     *     tags={"job"},
     *     description="HTTP Status Code indicates if the job is done already",
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Id of the job which status you wandt to see",
     *         required=true,
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(response="200",
     *     description="Job has no peding executions",
     *         @OA\JsonContent(
     *             @OA\Schema(ref="#/components/schemas/default-content")
     *         )
     *     ),
     *     @OA\Response(response="424",
     *     description="Job has panding and/or running executions",
     *         @OA\JsonContent(
     *             @OA\Schema(ref="#/components/schemas/default-content")
     *         )
     *     )
     * )
     *
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/isdone", methods={"GET"}, name="exec.job.status")
     */
    public function isDoneAction(): ResponseInterface
    {
        if (null === $this->job->getId()) {
            return $this->render(['success' => false, 'message' => 'Job not found'], StatusCode::HTTP_NOT_FOUND);
        }

        $executionsTotal = App::getDbHelper()->getRepository(Execution::class)->count(['job' => $this->job]);
        $executionsPending = App::getDbHelper()->getRepository(Execution::class)->count(['job' => $this->job, 'status' => ExecutionStatus::READY]);

        return $this->render([], $executionsTotal > 0 && 0 === $executionsPending ? StatusCode::HTTP_OK : StatusCode::HTTP_FAILED_DEPENDENCY);
    }

    /**
     * @OA\Get(
     *     path="/job/logs",
     *     tags={"job"},
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     description="Aggregation of the logs of all executions of a job",
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Id of the job which logs you want to see",
     *         required=true,
     *         @Oa\Schema(
     *             type="integer",
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="Amount of log entries to retreive",
     *         @Oa\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Amount of log entries to skip",
     *         @Oa\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="cursor",
     *         in="query",
     *         description="Retrieve results specified after this cursor",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         @OA\Schema(
     *             enum={"asc", "desc"},
     *             default="desc"
     *         )
     *     ),
     *     @OA\Response(response="200", ref="#/components/responses/logs")
     * )
     *
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/logs", methods={"GET"}, name="job.logs")
     */
    public function logsAction(): ResponseInterface
    {
        if (null === $this->job->getId()) {
            return $this->render(['success' => false, 'message' => 'Job not found'], StatusCode::HTTP_NOT_FOUND);
        }

        $requestParams = Log::fromParams($this->params);
        $data = $this->logService->retrieveLogs($this->job->getOwner()->getId(), $requestParams, $this->job->getId());

        return $this->render($data);
    }

    /**
     * @return ResponseInterface
     *
     * @throws Exception
     *
     * @Route("/callback", methods={"POST", "GET"}, "name="job.callback")
     */
    public function callbackAction(): ResponseInterface
    {
        if (null === $this->job->getId()) {
            return $this->render(['success' => false, 'message' => 'Job not found'], StatusCode::HTTP_NOT_FOUND);
        }

        $body = $this->request->getParsedBody();
        LogHelper::debug('Body received into job ' . $this->job->getId() . ' callback:' . print_r($body, true));

        if (array_key_exists('error', $body)) {
            // TODO: Remove this notification because it's only a debug help for Kevin
            App::getNotificationUtility()::notifyAdmin('Error Callback received in Panel: ' . print_r($body, true));

            LogHelper::warn('Error Callback received for job ' . $this->job->getId() . ' schedule retry...');
            switch ($this->job->getStatus()) {
                case JobStatus::INIT:
                    $this->job->setStatus(JobStatus::INIT_ERROR);
                    break;
                case JobStatus::DELETING:
                    $this->job->setStatus(JobStatus::DELETING_ERROR);
                    break;
                default:
                    throw new HttpException(StatusCode::HTTP_NOT_ACCEPTABLE, 'Error Callback received for Job with undetermined status');
                    break;
            }
            $this->persistJob();
            return $this->render(['success' => true, 'message' => 'Error recorded. Thanks.']);
        }

        // remember manager nodes.
        // TODO CB: Fix this. $body['nodes'] should actually be a full array with all the tokens etc. for every managernode
        if (array_key_exists('nodes', $body)) {
            $nodes = is_array($body['nodes']) ? $body['nodes'] : [$body['nodes']];
            foreach ($nodes as $node) {
                $manager = App::getDbHelper()->getRepository(Manager::class)->findOneBy(['fqdn' => $node]);
                if (array_key_exists('deleted', $body)) {
                    /** @var Manager $manager */
                    if ($manager) {
                        $this->job->setManager();
                        $manager->setStatus(ManagerStatus::REMOVED);
                    } else {
                        $this->job->removeManagerNode($node);
                    }
                } else {
                    if (!$manager) {
                        $manager = new Manager();
                    }
                    $manager->setFqdn($node)
                        ->setIdByChoria($body['manager_id'] ?? '')
                        ->setStatus(ManagerStatus::READY)
                        ->setWorkerToken($body['swarm_token_worker'] ?? '')
                        ->setManagerToken($body['swarm_token_manager'] ?? '')
                        ->setIp($body['manager_ip'] ?? '');
                    $this->job->setManager($manager);
                }
            }
        }

        $this->persistJob();

        // provision missing redundancy nodes if necessary
        if (!array_key_exists('deleted', $body) && $this->job->getManager()->getIp()) {
            $managerName = OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->provisionManager();
            if ($this->job->getManager()) {
                $this->job->getManager()->setName($managerName);
            }
        }

        // finalize
        if ($this->job->getManager() || $this->job->getInitManagerIp() && $this->job->getClusterToken() && $this->job->getManagerToken() && count($this->job->getManagerNodes()) > 0) {
            $this->setJobReady($this->job, $this->job->getManager()->getName());
        }

        if (array_key_exists('deleted', $body) && !$this->job->getManager() && 0 === count($this->job->getManagerNodes())) {
            if (JobStatus::DELETING === $this->job->getStatus()) {
                $this->job->setStatus(JobStatus::DELETED);
            } elseif (JobStatus::READY_PAUSING === $this->job->getStatus()) {
                $this->job->setStatus(JobStatus::READY_PAUSED);
            } else {
                LogHelper::err(sprintf('This is highly irregular: Manager for job %s was deleted without proper removal status', $this->job->getId()));
            }
            if ($this->job->getOwner()->getPreferences()->getNotifications()->isEmailOnJobDeleted()) {
                App::getNotificationUtility()::notifyUser($this->job->getOwner(), sprintf('Job %s (%d) removed', $this->job->getName(), $this->job->getId()), 'Your job with the id ' . $this->job->getId() . ' is now completely removed from Helio');
            }
            if (!$this->job->getOwner()->getPreferences()->getNotifications()->isMuteAdmin()) {
                App::getNotificationUtility()::notifyAdmin('Job was deleted by ' . $this->job->getOwner()->getEmail() . ', type: ' . $this->job->getType() . ', id: ' . $this->job->getId() . ', expected manager: manager-init-' . ServerUtility::getShortHashOfString($this->job->getId()));
            }
        }

        $this->persistJob();

        return $this->render(['message' => 'ok']);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/manager/init", methods={"GET"}, name="job.manager.init")
     */
    public function getInitManagerNodeConfigAction(): ResponseInterface
    {
        $config = [
            'classes' => ['role::base', 'profile::docker'],
            'profile::docker::manager' => true,
            'profile::docker::manager_init' => true,
        ];

        return $this
            ->setReturnType('yaml')
            ->render($config);
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/manager/redundancy", methods={"GET"}, name="job.manager.redundancy")
     */
    public function getRedundantManagerNodeConfigAction(): ResponseInterface
    {
        if (null === $this->job->getId()) {
            return $this->render(['success' => false, 'message' => 'Job not found'], StatusCode::HTTP_NOT_FOUND);
        }

        $manager = $this->job->getManager();
        $config = [
            'classes' => ['role::base', 'profile::docker'],
            'profile::docker::manager' => true,
            'profile::docker::manager_init' => true,
            'profile::docker::manager_ip' => $manager ? $manager->getIp() : $this->job->getInitManagerIp(),
            'profile::docker::token' => $manager ? $manager->getWorkerToken() : $this->job->getClusterToken(),
        ];

        return $this
            ->setReturnType('yaml')
            ->render($config);
    }

    /**
     * This action is only used in the UI upon an "abort" click in the "add Job" wizard.
     * Therefore, it's not documented in the API doc.
     *
     * @return ResponseInterface
     *
     * @Route("/add/abort", methods={"POST"}, name="job.abort")
     *
     * @throws Exception
     */
    public function abortAddJobAction(): ResponseInterface
    {
        if (null === $this->job->getId()) {
            return $this->render(['success' => false, 'message' => 'Job not found'], StatusCode::HTTP_NOT_FOUND);
        }

        if ($this->job && JobStatus::UNKNOWN === $this->job->getStatus() && $this->job->getOwner() && $this->job->getOwner()->getId() === $this->user->getId()) {
            $this->user->removeJob($this->job);
            App::getDbHelper()->remove($this->job);
            App::getDbHelper()->flush();
            $this->persistUser();

            return $this->render();
        }

        return $this->render(['message' => 'no access to job'], StatusCode::HTTP_UNAUTHORIZED);
    }

    protected function setJobReady(Job $job, string $managerName): void
    {
        $job->setStatus(JobStatus::READY);
        if ($job->getOwner()->getPreferences()->getNotifications()->isEmailOnJobReady()) {
            App::getNotificationUtility()::notifyUser(
                $job->getOwner(),
                sprintf('Job %s (%d) ready', $job->getName(), $job->getId()),
                sprintf('Your job with the id %s is now ready to be executed on Helio', $job->getId())
            );
        }
        if (!$job->getOwner()->getPreferences()->getNotifications()->isMuteAdmin()) {
            App::getNotificationUtility()::notifyAdmin(
                sprintf(
                    'Job is now ready. By: %s, type: %s, id: %s, expected manager: %s',
                    $job->getOwner()->getEmail(),
                    $job->getType(),
                    $job->getId(),
                    $managerName
                )
            );
        }
    }
}
