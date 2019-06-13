<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\InstanceController;
use Helio\Panel\Controller\Traits\TaskController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Controller\Traits\ValidatedJobController;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Task;
use Helio\Panel\Model\User;
use Helio\Panel\Orchestrator\OrchestratorFactory;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\JwtUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Http\StatusCode;

/**
 * Class ApiController
 *
 * @package    Helio\Panel\Controller
 * @author    Christoph Buchli <team@opencomputing.cloud>
 *
 * @RoutePrefix('/exec')
 *
 */
class ExecController extends AbstractController
{
    use ValidatedJobController, TaskController, InstanceController {
        ValidatedJobController::setupParams insteadof TaskController, InstanceController;
        ValidatedJobController::requiredParameterCheck insteadof TaskController, InstanceController;
        ValidatedJobController::optionalParameterCheck insteadof TaskController, InstanceController;
    }
    use TypeApiController;


    /**
     * @var User needed for auth by user token
     */
    protected $user;


    public function setupUser(): bool
    {
        $this->setupParams();
        if (\array_key_exists('token', $this->params)) {
            /** @var User $tryToFindUser */
            $tryToFindUser = $this->dbHelper->getRepository(User::class)->findOneByToken($this->params['token']);
            if ($tryToFindUser !== null && $tryToFindUser->isAdmin() && JwtUtility::verifyUserIdentificationToken($tryToFindUser, $this->params['token'])) {
                $this->user = $tryToFindUser;
            }
        }
        return true;
    }


    /**
     * @return ResponseInterface
     *
     * @Route("", methods={"POST", "PUT", "GET", "DELETE"}, name="job.exec")
     */
    public function execAction(): ResponseInterface
    {
        try {
            if (!JobStatus::isValidActiveStatus($this->job->getStatus())) {
                throw new \RuntimeException('job not ready');
            }

            // run and stop have the same interface, thus we can reuse the code
            $command = 'run';
            if ($this->request->getMethod() === 'DELETE') {
                $command = 'stop';
            }

            // run the job and check if the replicas have changed
            $previousReplicaCount = JobFactory::getDispatchConfigOfJob($this->job, $this->task)->getDispatchConfig()->getReplicaCountForJob($this->job);
            JobFactory::getInstanceOfJob($this->job, $this->task)->$command($this->params, $this->request, $this->response);
            $newReplicaCount = JobFactory::getDispatchConfigOfJob($this->job, $this->task)->getDispatchConfig()->getReplicaCountForJob($this->job);

            // if replica count has changed OR we have an enforcement (e.g. one replica per task fixed), dispatch the job
            if ($previousReplicaCount !== $newReplicaCount || JobFactory::getDispatchConfigOfJob($this->job, $this->task)->getDispatchConfig()->getFixedReplicaCount()) {
                OrchestratorFactory::getOrchestratorForInstance($this->instance)->dispatchJob($this->job);
                $this->persistTask();
                $this->persistJob();
            }

            return $this->render(['status' => 'success']);
        } catch (\Exception $e) {
            return $this->render(['status' => 'error', 'reason' => $e->getMessage()], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/jobstatus", methods={"GET"}, name="exec.job.status")
     */
    public function jobStatusAction(): ResponseInterface
    {
        return $this->render([
            'status' => $this->job->getStatus(),
            'tasks' => [
                'total' => $this->dbHelper->getRepository(Task::class)->count(['job' => $this->job]),
                'pending' => $this->dbHelper->getRepository(Task::class)->count(['job' => $this->job, 'status' => TaskStatus::READY]),
                'running' => $this->dbHelper->getRepository(Task::class)->count(['job' => $this->job, 'status' => TaskStatus::RUNNING]),
                'done' => $this->dbHelper->getRepository(Task::class)->count(['job' => $this->job, 'status' => TaskStatus::DONE])
            ]
        ]);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/isdone", methods={"GET"}, name="exec.job.status")
     */
    public function jobIsDoneAction(): ResponseInterface
    {
        $tasksPending = $this->dbHelper->getRepository(Task::class)->count(['job' => $this->job, 'status' => TaskStatus::READY]);
        return $this->render([], $tasksPending === 0 ? StatusCode::HTTP_OK : StatusCode::HTTP_FAILED_DEPENDENCY);
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/taskstatus", methods={"GET"}, name="exec.task.status")
     */
    public function taskStatusAction(): ResponseInterface
    {
        if (!$this->task) {
            return $this->render(['error' => 'no task specified']);
        }
        return $this->render([
            'status' => $this->job->getStatus()
        ]);
    }


    /**
     * @return ResponseInterface
     * @param string $method any method of the respective job object
     *
     * @Route("/work/{method:[\w]+}", methods={"GET", "POST", "PUT"}, name="job.work")
     */
    public function workAction(string $method): ResponseInterface
    {
        try {
            if (!JobStatus::isValidActiveStatus($this->job->getStatus())) {
                throw new \RuntimeException('job not ready');
            }
            return JobFactory::getInstanceOfJob($this->job, $this->task)->$method($this->params, $this->response, $this->request);
        } catch (\Exception $e) {
            return $this->render(['status' => 'error'], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * @return ResponseInterface
     *
     * @Route("/heartbeat", methods={"GET", "POST", "PUT"}, name="job.heartbeat")
     */
    public function heartbeatAction(): ResponseInterface
    {
        try {
            if (!$this->task) {
                return $this->render(['error' => 'unknown task', 'params' => $this->params, 'task' => $this->task, StatusCode::HTTP_NOT_FOUND]);
            }
            $this->task->setLatestAction()->setStatus(TaskStatus::RUNNING);
            $this->dbHelper->persist($this->task);
            $this->dbHelper->flush();
            return $this->render();
        } catch (\Exception $e) {
            return $this->render(['status' => 'error'], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }
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
        if ($uploadedFile && $uploadedFile->getError() === UPLOAD_ERR_OK) {
            $uploadedFile->moveTo(ExecUtility::getJobDataFolder($this->job) . $uploadedFile->getClientFilename());
            return $this->render();
        }
        return $this->render(['error' => $uploadedFile->getError()], StatusCode::HTTP_FAILED_DEPENDENCY);
    }


    /**
     * @return ResponseInterface
     * @param string $file
     *
     * @Route("/download/{file:[\w\.]+}", methods={"GET"}, name="job.download")
     */
    public function downloadAction(string $file): ResponseInterface
    {
        return ExecUtility::downloadFile(ExecUtility::getJobDataFolder($this->job) . $file, $this->response);
    }
}
