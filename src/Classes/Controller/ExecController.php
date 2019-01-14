<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\TaskController;
use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Controller\Traits\ValidatedJobController;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\ExecUtility;
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
    use ValidatedJobController, TaskController {
        ValidatedJobController::setupParams insteadof TaskController;
        ValidatedJobController::requiredParameterCheck insteadof TaskController;
        ValidatedJobController::optionalParameterCheck insteadof TaskController;
    }
    use TypeApiController;


    /**
     * @return ResponseInterface
     *
     * @Route("", methods={"POST", "PUT", "GET"}, name="job.exec")
     */
    public function execAction(): ResponseInterface
    {
        try {
            JobFactory::getInstanceOfJob($this->job, $this->task)->run($this->params, $this->request, $this->response);
            return $this->render(['status' => 'success']);
        } catch (\Exception $e) {
            return $this->render(['status' => 'error', 'reason' => $e->getMessage()], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * @return ResponseInterface
     *
     * @Route("", methods={"DELETE"}, name="job.stop")
     */
    public function stopAction(): ResponseInterface
    {
        try {
            JobFactory::getInstanceOfJob($this->job, $this->task)->stop($this->params);
            return $this->render(['status' => 'success']);
        } catch (\Exception $e) {
            return $this->render(['status' => 'error'], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }
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
            return JobFactory::getInstanceOfJob($this->job, $this->task)->$method($this->params, $this->response);
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
