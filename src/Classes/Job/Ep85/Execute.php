<?php

namespace Helio\Panel\Job\Ep85;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use Helio\Panel\Controller\ExecController;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Job\JobInterface;
use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Execute implements JobInterface
{
    /**
     * @var Job
     */
    protected $job;

    /**
     * Execute constructor.
     * @param Job $job
     */
    public function __construct(Job $job)
    {
        $this->job = $job;
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
        $config = array_merge(json_decode($request->getBody()->getContents(), true) ?? [],
            [
                'idf' => ExecController::getExecUrl($this->job, 'work/getidfdata'),
                'idf_sha1' => $this->getidfsum() . '-' . bin2hex(random_bytes(4)),
                'epw' => ExecController::getExecUrl($this->job, 'work/getwetherdata'),
                'epw_sha1' => $this->getwethersum(),
                'report' => ExecController::getExecUrl($this->job, 'work/submitresult'),
                'upload' => ExecController::getExecUrl($this->job, 'upload'),
            ]);
        $task = (new Task())
            ->setStatus(TaskStatus::READY)
            ->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()))
            ->setJob($this->job)
            ->setConfig(json_encode($config, JSON_UNESCAPED_SLASHES));

        DbHelper::getInstance()->persist($task);
        DbHelper::getInstance()->flush($task);

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
                $config['id'] = (string)$lockedTask->getId();
                return $response->withJson($config, null, JSON_UNESCAPED_SLASHES);
            } catch (OptimisticLockException $e) {
                // trying next task
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
        return ExecController::downloadFile(ExecController::getJobDataFolder($this->job) . 'weather.epw', $response);
    }

    /**
     * @param array $params
     * @param Response $response
     * @return ResponseInterface
     */
    public function getidfdata(array $params, Response $response): ResponseInterface
    {
        return ExecController::downloadFile(ExecController::getJobDataFolder($this->job) . 'parameters.idf', $response);
    }


    /**
     * @return string
     */
    protected function getwethersum(): string
    {
        return sha1_file(ExecController::getJobDataFolder($this->job) . 'weather.epw');
    }

    /**
     * @return string
     */
    protected function getidfsum(): string
    {
        return sha1_file(ExecController::getJobDataFolder($this->job) . 'parameters.idf');
    }


    /**
     * @param array $params
     * @param Response $response
     * @return ResponseInterface
     */
    public function submitresult(array $params, Response $response): ResponseInterface
    {
        if (\array_key_exists('success', $params) && \array_key_exists('taskid', $params) && $params['success']) {
            /** @var Task $task */
            $task = DbHelper::getInstance()->getRepository(Task::class)->findOneById($params['taskid']);
            $task->setStatus(TaskStatus::DONE);
            DbHelper::getInstance()->persist($task);
            DbHelper::getInstance()->flush();
            return $response;
        }
        return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
    }
}