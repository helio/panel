<?php

namespace Helio\Panel\Controller;

use Exception;
use Helio\Panel\Helper\DbHelper;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Helio\Panel\App;
use Helio\Panel\Controller\Traits\HelperElasticController;
use Helio\Panel\Controller\Traits\ModelInstanceController;
use Helio\Panel\Controller\Traits\ModelExecutionController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Controller\Traits\AuthorizedActiveJobController;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Utility\ExecUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * Class ApiController.
 *
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/api/job/{jobid:[\d]+}/execute')
 */
class ApiJobExecuteController extends AbstractController
{
    use AuthorizedActiveJobController, ModelExecutionController, ModelInstanceController {
        AuthorizedActiveJobController::setupParams insteadof ModelExecutionController, ModelInstanceController;
        AuthorizedActiveJobController::requiredParameterCheck insteadof ModelExecutionController, ModelInstanceController;
        AuthorizedActiveJobController::optionalParameterCheck insteadof ModelExecutionController, ModelInstanceController;

        AuthorizedActiveJobController::setupUser insteadof ModelInstanceController, ModelExecutionController;
        AuthorizedActiveJobController::validateUserIsSet insteadof ModelInstanceController, ModelExecutionController;
        AuthorizedActiveJobController::persistUser insteadof ModelInstanceController, ModelExecutionController;

        AuthorizedActiveJobController::setupJob insteadof ModelInstanceController, ModelExecutionController;
        AuthorizedActiveJobController::validateJobIsSet insteadof ModelInstanceController, ModelExecutionController;
        AuthorizedActiveJobController::persistJob insteadof ModelInstanceController, ModelExecutionController;
    }

    use HelperElasticController;
    use TypeApiController;

    /**
     * @return string
     */
    protected function getIdAlias(): string
    {
        return 'executionid';
    }

    /**
     * @OA\Post(
     *     path="/job/{jobid}/execute",
     *     description="Executes a Job and therefore creates an execution environment. This may take a while!",
     *     security={
     *         {"authByJobtoken": {"any"}},
     *         {"authByApitoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="jobid",
     *         in="path",
     *         description="Id of the job to execute",
     *         required=true,
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *
     *     @OA\RequestBody(
     *         description=">- Job Type Specific configuration, formated like this

        {
            ""env"": [
                {""SOURCE_PATH"":""https://account-name.zone-name.web.core.windows.net""},
                {""TARGET_PATH"":""https://bucket.s3.aws-region.amazonaws.com""}
            ]
        }",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="string"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="200", description="Create a Job",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="string",
     *                 description="boolean if the execution was successful"
     *             ),
     *             @OA\Property(
     *                 property="id",
     *                 type="string",
     *                 description="The Id of the newly created execution"
     *             )
     *         )
     *     )
     * ),
     *
     * @OA\Delete(
     *     path="/job/{jobid}/execute",
     *     description="Removes an execution and destroys that specific execution environment. This may take a while!",
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="jobid",
     *         in="path",
     *         description="Id of the job, used to authenticate",
     *         required=true,
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Id of the Execution to delete",
     *         required=true,
     *         @Oa\Items(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(response="200", ref="#/components/responses/200"),
     *     @OA\Response(response="500", ref="#/components/responses/500")
     * )
     *
     * @return ResponseInterface
     *
     * @Route("", methods={"POST", "PUT", "DELETE"}, name="job.exec")
     */
    public function execAction(): ResponseInterface
    {
        try {
            if (!JobStatus::isValidActiveStatus($this->job->getStatus())) {
                throw new RuntimeException('job not ready');
            }

            // run and stop have the same interface, thus we can reuse the code
            $command = 'run';
            if ('DELETE' === $this->request->getMethod()) {
                $command = 'stop';
                $estimates = [];
            } else {
                $this->execution->setName(array_key_exists('name', $this->params) ? $this->params['name'] : 'automatically created');
                $this->persistExecution();
                $estimates = ['estimates' => JobFactory::getDispatchConfigOfJob($this->job, $this->execution)->getExecutionEstimates()];
            }

            // run the job and check if the replicas have changed
            $previousReplicaCount = JobFactory::getDispatchConfigOfJob($this->job, $this->execution)->getDispatchConfig()->getReplicaCountForJob($this->job);
            JobFactory::getInstanceOfJob($this->job, $this->execution)->$command(json_decode((string) $this->request->getBody(), true) ?: []);
            $newReplicaCount = JobFactory::getDispatchConfigOfJob($this->job, $this->execution)->getDispatchConfig()->getReplicaCountForJob($this->job);

            // if replica count has changed OR we have an enforcement (e.g. one replica per execution fixed), dispatch the job
            if ($previousReplicaCount !== $newReplicaCount || JobFactory::getDispatchConfigOfJob($this->job, $this->execution)->getDispatchConfig()->getFixedReplicaCount()) {
                OrchestratorFactory::getOrchestratorForInstance($this->instance, $this->job)->dispatchJob();
                $this->persistExecution();
                $this->persistJob();
            }

            return $this->render(array_merge([
                'status' => 'success',
                'id' => $this->execution->getId(),
            ], $estimates));
        } catch (Exception $e) {
            return $this->render(['success' => false, 'message' => $e->getMessage()], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *    path="/job/{jobid}/execute",
     *     security={
     *         {"authByJobtoken": {"any"}},
     *         {"authByApitoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Id of the current Execution",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="jobid",
     *         in="path",
     *         description="Id of the job that the execution belongs to",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(response="200", description="Get a Job",
     *         @OA\JsonContent(
     *           type="object",
     *           @OA\Property(
     *               property="success",
     *               type="string",
     *               description="boolean if the execution was successful"
     *           )
     *         )
     *     )
     * )
     *
     * @return ResponseInterface
     *
     * @Route("", methods={"GET"}, name="exec.execution.get")
     */
    public function executionStatusAction(): ResponseInterface
    {
        if (!$this->execution) {
            return $this->render(['error' => 'no execution specified'], StatusCode::HTTP_NOT_FOUND);
        }

        return $this->render([
            'success' => true,
            'id' => $this->execution->getId(),
            'priority' => $this->execution->getPriority(),
            'results' => $this->execution->getStats(),
            'latestHeartbeat' => $this->execution->getLatestHeartbeat(),
            'message' => 'The Status of your execution is ' . ExecutionStatus::getLabel($this->execution->getStatus()),
            'status' => $this->execution->getStatus(),
            'estimates' => JobFactory::getDispatchConfigOfJob($this->job, $this->execution)->getExecutionEstimates(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/job/{jobid}/execute/submitresult",
     *     security={
     *         {"authByJobtoken": {"any"}},
     *         {"authByApitoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Id of the current Execution",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="jobid",
     *         in="path",
     *         description="Id of the job that the execution belongs to",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         description=">- Arbitrary Job result data as JSON, for example:

        {
            ""success"":true,
            ""result"":42
        }",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="string"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response="200", description="Create a Job"),
     *     @OA\Response(response="404", ref="#/components/responses/404")
     * )
     *
     * @param array            $params
     * @param Response         $response
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @Route("/submitresult", methods={"POST"}, name="job.exec.submitresult")
     */
    public function submitresult(array $params, Response $response, RequestInterface $request): ResponseInterface
    {
        if ('__NEW' !== $this->execution->getName()) {
            /* @var Execution $execution */
            $this->execution->setStatus(ExecutionStatus::DONE);
            $this->execution->setStats((string) $request->getBody());
            DbHelper::getInstance()->persist($this->execution);
            DbHelper::getInstance()->flush();

            return $response;
        }

        return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
    }

    /**
     * @param string $method any method of the respective job object
     *
     * @Route("/work/{method:[\w]+}", methods={"GET", "POST", "PUT"}, name="job.exec.work")
     *
     * @return ResponseInterface
     */
    public function workAction(string $method): ResponseInterface
    {
        try {
            if (!JobStatus::isValidActiveStatus($this->job->getStatus())) {
                throw new RuntimeException('job not ready');
            }

            return JobFactory::getInstanceOfJob($this->job, $this->execution)->$method($this->params, $this->response, $this->request);
        } catch (Exception $e) {
            return $this->render(['status' => 'error'], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/heartbeat", methods={"POST", "PUT"}, name="job.heartbeat")
     */
    public function heartbeatAction(): ResponseInterface
    {
        try {
            if (!$this->execution) {
                return $this->render(['error' => 'unknown execution', 'params' => $this->params, 'execution' => $this->execution, StatusCode::HTTP_NOT_FOUND]);
            }
            $this->execution->setLatestAction()->setStatus(ExecutionStatus::RUNNING);
            App::getDbHelper()->persist($this->execution);
            App::getDbHelper()->flush();

            return $this->render();
        } catch (Exception $e) {
            return $this->render(['status' => 'error'], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/job/{jobid}/execute/logs",
     *     description="Logs of an execution",
     *     security={
     *         {"authByApitoken": {"any"}},
     *         {"authByJobtoken": {"any"}}
     *     },
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Id of the execution which logs you wandt to see",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="jobid",
     *         in="path",
     *         description="Id of the associated job, needed for authentication and authorisation",
     *         required=true,
     *         @Oa\Items(
     *             type="string"
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
     *     @OA\Response(response="200", ref="#/components/responses/logs"),
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
        if (!$this->execution) {
            return $this->render([]);
        }

        /** @var Job $job */
        $job = $this->execution->getJob();
        if (!$job->getOwner()) {
            return $this->render([]);
        }

        return $this->render($this->setWindow()->getLogEntries($job->getOwner()->getId(), $job->getId(), $this->execution->getId()));
    }

    /**
     * @return ResponseInterface
     *
     * @Route("/upload", methods={"POST"}, name="job.upload")
     */
    public function uploadAction(): ResponseInterface
    {
        /** @var UploadedFileInterface $uploadedFile */
        $uploadedFile = $this->request->getUploadedFiles()['file'];
        if ($uploadedFile && UPLOAD_ERR_OK === $uploadedFile->getError()) {
            $uploadedFile->moveTo(ExecUtility::getJobDataFolder($this->job) . $uploadedFile->getClientFilename());

            return $this->render();
        }

        return $this->render(['error' => $uploadedFile->getError()], StatusCode::HTTP_FAILED_DEPENDENCY);
    }

    /**
     * @param string $file
     *
     * @Route("/download/{file:[\w\.]+}", methods={"GET"}, name="job.download")
     *
     * @return ResponseInterface
     */
    public function downloadAction(string $file): ResponseInterface
    {
        return ExecUtility::downloadFile(ExecUtility::getJobDataFolder($this->job) . $file, $this->response);
    }
}
