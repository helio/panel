<?php

namespace Helio\Panel\Controller;


use Helio\Panel\Controller\Traits\TypeApiController;
use Helio\Panel\Controller\Traits\ValidatedJobController;
use Helio\Panel\Helper\LogHelper;
use Helio\Panel\Job\JobFactory;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\ServerUtility;
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
    use ValidatedJobController;
    use TypeApiController;


    /**
     * @return ResponseInterface
     *
     * @Route("", methods={"POST", "PUT", "GET"}, name="job.exec")
     */
    public function execAction(): ResponseInterface
    {
        try {
            JobFactory::getInstanceOfJob($this->job)->run($this->params, $this->request, $this->response);
            return $this->render(['status' => 'success']);
        } catch (\Exception $e) {
            return $this->render(['status' => 'error'], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
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
            JobFactory::getInstanceOfJob($this->job)->stop($this->params);
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
            return JobFactory::getInstanceOfJob($this->job)->$method($this->params, $this->response);
        } catch (\Exception $e) {
            return $this->render(['status' => 'error'], StatusCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * @return ResponseInterface
     * @param int $task
     *
     * @Route("/heartbeat/{task:[\d]+}", methods={"GET", "POST", "PUT"}, name="job.heartbeat")
     */
    public function heartbeatAction(int $task): ResponseInterface
    {
        try {
            /** @var Task $task */
            $task = $this->dbHelper->getRepository(Task::class)->findOneById($task);
            if (!$task) {
                return $this->render(['error' => 'unknown task', StatusCode::HTTP_NOT_FOUND]);
            }
            $task->setLatestAction()->setStatus(TaskStatus::RUNNING);
            $this->dbHelper->flush($task);
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
        LogHelper::addCritical('entered');
        if ($uploadedFile && $uploadedFile->getError() === UPLOAD_ERR_OK) {
            $uploadedFile->moveTo(self::getJobDataFolder($this->job) . $uploadedFile->getClientFilename());
            LogHelper::addCritical('done');
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
        return self::downloadFile(self::getJobDataFolder($this->job) . $file, $this->response);
    }


    /**
     * @param Job $job
     * @param string $endpoint
     * @param bool $addJobParameter
     * @return string
     */
    public static function getExecUrl(Job $job, string $endpoint = '', bool $addJobParameter = false): string
    {
        if ($endpoint && strpos($endpoint, '/') !== 0) {
            $endpoint = '/' . $endpoint;
        }
        return "exec$endpoint" . ($addJobParameter ? '?jobid=' . $job->getId() . '&token=' . $job->getToken() : '');
    }

    /**
     * @param Job $job
     * @return string
     */
    public static function getJobDataFolder(Job $job): string
    {
        $folder = ServerUtility::getTmpPath() . DIRECTORY_SEPARATOR . 'jobdata' . DIRECTORY_SEPARATOR . $job->getId() . DIRECTORY_SEPARATOR;
        if (!\is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $folder));
        }
        return $folder;
    }

    /**
     * @param string $file
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public static function downloadFile(string $file, ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withoutHeader('Content-Description')->withHeader('Content-Description', 'File Transfer')
            ->withoutHeader('Content-Type')->withHeader('Content-Type', 'application/octet-stream')
            ->withoutHeader('Content-Disposition')->withHeader('Content-Disposition', 'attachment; filename="' . basename($file) . '"')
            ->withoutHeader('Expires')->withHeader('Expires', '0')
            ->withoutHeader('Cache-Control')->withHeader('Cache-Control', 'must-revalidate')
            ->withoutHeader('Pragma')->withHeader('Pragma', 'public')
            ->withoutHeader('Content-Length')->withHeader('Content-Length', filesize($file))
            ->withBody(new \GuzzleHttp\Psr7\LazyOpenStream($file, 'r'));
    }
}
