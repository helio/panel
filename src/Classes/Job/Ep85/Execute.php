<?php

namespace Helio\Panel\Job\Ep85;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Job\DispatchableInterface;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Job\JobInterface;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Execute implements JobInterface, DispatchableInterface
{
    /**
     * @var Job
     */
    protected $job;
    /**
     * @var Task
     */
    protected $task;

    /**
     * Execute constructor.
     *
     * @param Job $job
     * @param Task|null $task
     */
    public function __construct(Job $job, Task $task = null)
    {
        $this->job = $job;
        $this->task = $task;
    }


    /**
     * @param array $params
     * @return bool
     */
    public function create(array $params): bool
    {
        return true;
    }


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return bool
     * @throws \Exception
     */
    public function run(array $params, RequestInterface $request, ResponseInterface $response): bool
    {
        $this->task = (new Task())->setJob($this->job)->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()))->setConfig('');
        DbHelper::getInstance()->persist($this->task);
        DbHelper::getInstance()->flush();

        /** @var Request $request */
        /** @var UploadedFileInterface $uploadedFile */
        $uploadedFile = $request->getUploadedFiles()['idf'];
        if ($uploadedFile && $uploadedFile->getError() === UPLOAD_ERR_OK) {
            $idf = ExecUtility::getTaskDataFolder($this->task) . $uploadedFile->getClientFilename();
            $uploadedFile->moveTo($idf);
        } else {
            $idf = ExecUtility::getJobDataFolder($this->job) . 'example.idf';
            if (!file_exists($idf)) {
                copy(__DIR__ . DIRECTORY_SEPARATOR . 'example.idf', $idf);
            }
        }

        if (\array_key_exists('epw', $params) && $params['epw']) {
            $epw = ExecUtility::getTaskDataFolder($this->task) . 'weather.epw';
            filter_var($params['epw'], FILTER_VALIDATE_URL);
            file_put_contents($epw, fopen($params['epw'], 'rb'));
        } else {
            $epw = ExecUtility::getJobDataFolder($this->job) . 'weather.epw';
            if (!file_exists($epw)) {
                copy(__DIR__ . DIRECTORY_SEPARATOR . 'example.epw', $epw);
            }
        }
        $config = array_merge(json_decode($request->getBody()->getContents(), true) ?? [],
            [
                'idf' => $idf,
                'idf_sha1' => ServerUtility::getSha1SumFromFile($idf),
                'epw' => $epw,
                'epw_sha1' => ServerUtility::getSha1SumFromFile($epw),
            ]);

        $this->task->setStatus(TaskStatus::READY)
            ->setConfig(json_encode($config, JSON_UNESCAPED_SLASHES));

        DbHelper::getInstance()->persist($this->task);
        DbHelper::getInstance()->flush();

        return true;
    }

    /**
     * @param array $params
     * @return bool
     */
    public function stop(array $params): bool
    {
        $tasks = DbHelper::getInstance()->getRepository(Task::class)->findByJob($this->job);
        /** @var Task $task */
        foreach ($tasks as $task) {
            $task->setStatus(TaskStatus::STOPPED);
            DbHelper::getInstance()->persist($task);
        }
        DbHelper::getInstance()->flush();

        return true;
    }

    /**
     * @param array $params
     * @param Response $response
     * @return ResponseInterface
     * @throws \Exception
     */
    public function getnextinqueue(array $params, Response $response): ResponseInterface
    {
        $tasks = DbHelper::getInstance()->getRepository(Task::class)->findBy(['job' => $this->job, 'status' => TaskStatus::READY], ['priority' => 'ASC', 'created' => 'ASC'], 5);
        /** @var Task $task */
        foreach ($tasks as $task) {
            try {
                /** @var Task $lockedTask */
                $lockedTask = DbHelper::getInstance()->getRepository(Task::class)->find($task->getId(), LockMode::OPTIMISTIC, $task->getVersion());
                $lockedTask->setStatus(TaskStatus::RUNNING);
                DbHelper::getInstance()->flush();
                $config = json_decode($lockedTask->getConfig(), true);
                return $response->withJson([
                    'id' => (string)$lockedTask->getId(),
                    'idf' => ExecUtility::getExecUrl('work/getidfdata'),
                    'idf_sha1' => $config['idf_sha1'],
                    'epw' => ExecUtility::getExecUrl('work/getwetherdata'),
                    'epw_sha1' => $config['epw_sha1'],
                    'report' => ExecUtility::getExecUrl('work/submitresult'),
                    'upload' => ExecUtility::getExecUrl('upload'),
                ], null, JSON_UNESCAPED_SLASHES);
            } catch (OptimisticLockException $e) {
                // trying next task if the current one was modified in the meantime
            }
        }
        return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
    }


    /**
     * @param array $params
     * @param Response $response
     * @return ResponseInterface
     */
    public function getwetherdata(array $params, Response $response): ResponseInterface
    {
        return ExecUtility::downloadFile(json_decode($this->task->getConfig(), true)['epw'], $response);
    }

    /**
     * @param array $params
     * @param Response $response
     * @return ResponseInterface
     */
    public function getidfdata(array $params, Response $response): ResponseInterface
    {
        return ExecUtility::downloadFile(json_decode($this->task->getConfig(), true)['idf'], $response);
    }


    /**
     * @param array $params
     * @param Response $response
     * @return ResponseInterface
     */
    public function submitresult(array $params, Response $response): ResponseInterface
    {
        if ($this->task && \array_key_exists('success', $params) && $params['success']) {
            /** @var Task $task */
            $this->task->setStatus(TaskStatus::DONE);
            DbHelper::getInstance()->persist($this->task);
            DbHelper::getInstance()->flush();
            return $response;
        }
        return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
    }


    /**
     * @return DispatchConfig
     */
    public function getDispatchConfig(): DispatchConfig
    {
        return (new DispatchConfig())->setImage('gitlab.idling.host:4567/opencomputing/runner/ep85:latest')->setEnvVariables([
            'HELIO_JOBID' => $this->job->getId(),
            'HELIO_TOKEN' => $this->job->getToken(),
            'HELIO_URL' => ServerUtility::getBaseUrl()
        ]);
    }
}