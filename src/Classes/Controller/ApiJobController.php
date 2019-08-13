<?php

namespace Helio\Panel\Controller;

use Exception;
use Helio\Panel\App;
use Helio\Panel\Controller\Traits\HelperElasticController;
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
use Helio\Panel\Utility\NotificationUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\StatusCode;

/**
 * Class ApiController.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/job')
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

    use HelperElasticController;

    use TypeDynamicController;

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
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     @OA\RequestBody(ref="#/components/requestBodies/job"),
     *     @OA\Response(response="406", ref="#/components/responses/406"),
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
     *                 type="string",
     *                 description="The Id of the newly created job"
     *             ),
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 description="Indicates the success of the request"
     *             ),
     *             @OA\Property(
     *                 property="html",
     *                 type="string",
     *                 description="A HTML-rendered snipped of the job to embed in UIs"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="The Human readable success message"
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
     */
    public function addJobAction(): ResponseInterface
    {
        try {
            $this->requiredParameterCheck([
                'type' => FILTER_SANITIZE_STRING,
            ]);

            if (!JobType::isValidType($this->params['type'])) {
                return $this->render(['success' => false, 'message' => 'Unknown Job Type'], StatusCode::HTTP_NOT_ACCEPTABLE);
            }

            $this->job->setType($this->params['type']);
        } catch (Exception $e) {
            // If we have created a new job but haven't passed the jobtype (e.g. during wizard loading), we cannot continue.
            if ('___NEW' === $this->job->getName() && JobStatus::UNKNOWN === $this->job->getStatus()) {
                return $this->render(['token' => JwtUtility::generateToken(null, $this->user, null, $this->job)['token'], 'id' => $this->job->getId()]);
            }
            // if the existing job hasn't got a proper type, we cannot continue either, but that's a hard fail...
            if (!JobType::isValidType($this->job->getType())) {
                return $this->render(['success' => false, 'meassge' => $e->getMessage()], StatusCode::HTTP_NOT_ACCEPTABLE);
            }
        }

        $this->optionalParameterCheck([
            'jobname' => FILTER_SANITIZE_STRING,
            'cpus' => FILTER_SANITIZE_STRING,
            'gpus' => FILTER_SANITIZE_STRING,
            'location' => FILTER_SANITIZE_STRING,
            'billingReference' => FILTER_SANITIZE_STRING,
            'budget' => FILTER_SANITIZE_STRING,
            'free' => FILTER_SANITIZE_STRING,
            'config' => FILTER_SANITIZE_STRING,
        ]);

        JobFactory::getInstanceOfJob($this->job)->create($this->params);

        NotificationUtility::notifyAdmin('New Job was created by ' . $this->user->getEmail() . ', type: ' . $this->job->getType() . ', id: ' . $this->job->getId());

        OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->provisionManager();

        return $this->render([
            'success' => true,
            'token' => JwtUtility::generateToken(null, $this->user, null, $this->job)['token'],
            'id' => $this->job->getId(),
            'html' => $this->fetchPartial('listItemJob', ['job' => $this->job, 'user' => $this->user]),
            'message' => 'Job <strong>' . $this->job->getName() . '</strong> added',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/job",
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="jobid",
     *         in="query",
     *         description="Id of the job to delete",
     *         required=true,
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *     response="200", description="Job has been deleted",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 description="Indicates the success of the request"
     *             ),
     *             @OA\Property(
     *                 property="removed",
     *                 type="boolean",
     *                 description="Indicates whether the job has been deleted or cleanup still needs to be processed."
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 description="The Human readable success message"
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
        $removed = false;
        if (!JobType::isValidType($this->job->getType())) {
            $this->job->setHidden(true);
            $removed = true;
        } else {
            /* @var Execution $execution */
            JobFactory::getInstanceOfJob($this->job)->stop($this->params, $this->request, $this->response);

            // first: set all services to absent. then, remove the managers
            OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->dispatchJob();
            OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->removeManager();
        }

        // on PROD, we wait for the callbacks to confirm job removal. on Dev, simply set it to deleted.
        if (!ServerUtility::isProd()) {
            $this->job->setStatus(JobStatus::DELETED);
            $removed = true;
        }

        $this->persistJob();

        return $this->render(['success' => true, 'message' => 'Job scheduled for removal.', 'removed' => $removed]);
    }

    /**
     * @OA\Get(
     *     path="/job",
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
     *     @OA\Response(response="200", ref="#/components/responses/200")
     * )
     *
     * @return ResponseInterface
     *
     * @Route("", methods={"GET"}, name="exec.job.status")
     *
     * @throws Exception
     */
    public function jobStatusAction(): ResponseInterface
    {
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
     *     description="HTTP Status Code indicates if the job is properly provisioned and ready to be executed",
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
        $ready = JobStatus::READY === $this->job->getStatus();
        $status = $ready ? StatusCode::HTTP_OK : StatusCode::HTTP_FAILED_DEPENDENCY;
        $message = $ready ? 'Job is ready' : 'Execution environment for job is being prepared...';

        return $this->render(['success' => true, 'message' => $message], $status);
    }

    /**
     * @OA\Get(
     *     path="/job/isdone",
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
        $executionsTotal = App::getDbHelper()->getRepository(Execution::class)->count(['job' => $this->job]);
        $executionsPending = App::getDbHelper()->getRepository(Execution::class)->count(['job' => $this->job, 'status' => ExecutionStatus::READY]);

        return $this->render([], $executionsTotal > 0 && 0 === $executionsPending ? StatusCode::HTTP_OK : StatusCode::HTTP_FAILED_DEPENDENCY);
    }

    /**
     * @OA\Get(
     *     path="/job/logs",
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     description="Aggregation of the logs of all executions of a job",
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Id of the job which logs you wandt to see",
     *         required=true,
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="size",
     *         in="query",
     *         description="Amount of log entries to retreive",
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Amount of log entries to skip",
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(response="200", description="The Log Entries")
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
        return $this->render($this->setWindow()->getLogEntries($this->job->getOwner()->getId(), $this->job->getId()));
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
        $body = $this->request->getParsedBody();
        LogHelper::debug('Body received into job ' . $this->job->getId() . ' callback:' . print_r($body, true));

        // remember manager nodes.
        if (array_key_exists('nodes', $body)) {
            $nodes = is_array($body['nodes']) ? $body['nodes'] : [$body['nodes']];
            foreach ($nodes as $node) {
                if (array_key_exists('deleted', $body)) {
                    $this->job->removeManagerNode($node);
                } else {
                    $this->job->addManagerNode($node);
                }
            }
        }

        // remember swarm token
        if (array_key_exists('swarm_token_worker', $body)) {
            $this->job->setClusterToken($body['swarm_token_worker']);
        }
        if (array_key_exists('swarm_token_manager', $body)) {
            $this->job->setManagerToken($body['swarm_token_manager']);
        }

        // get manager IP
        if (array_key_exists('manager_ip', $body)) {
            $this->job->setInitManagerIp($body['manager_ip']);
        }

        $this->persistJob();

        // provision missing redundancy nodes if necessary
        if (!array_key_exists('deleted', $body) && $this->job->getInitManagerIp()) {
            OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->provisionManager();
        }

        // finalize
        // TODO: set redundancy to >= 3 again if needed
        if ($this->job->getInitManagerIp() && $this->job->getClusterToken() && $this->job->getManagerToken() && count($this->job->getManagerNodes()) > 0) {
            $this->job->setStatus(JobStatus::READY);
            NotificationUtility::notifyAdmin('Job is now read. By: ' . $this->user->getEmail() . ', type: ' . $this->job->getType() . ', id: ' . $this->job->getId());
        }
        if (array_key_exists('deleted', $body) && 0 === count($this->job->getManagerNodes())) {
            $this->job->setStatus(JobStatus::DELETED);
            NotificationUtility::notifyAdmin('Job was deleted by ' . $this->user->getEmail() . ', type: ' . $this->job->getType() . ', id: ' . $this->job->getId());
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
        $config = [
            'classes' => ['role::base', 'profile::docker'],
            'profile::docker::manager' => true,
            'profile::docker::manager_init' => true,
            'profile::docker::manager_ip' => $this->job->getInitManagerIp(),
            'profile::docker::token' => $this->job->getClusterToken(),
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
        if ($this->job && JobStatus::UNKNOWN === $this->job->getStatus() && $this->job->getOwner() && $this->job->getOwner()->getId() === $this->user->getId()) {
            $this->user->removeJob($this->job);
            App::getDbHelper()->remove($this->job);
            App::getDbHelper()->flush();
            $this->persistUser();

            return $this->render();
        }

        return $this->render(['message' => 'no access to job'], StatusCode::HTTP_UNAUTHORIZED);
    }
}
