<?php

namespace Helio\Panel\Job\Ep85;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\OptimisticLockException;
use Helio\Panel\App;
use Helio\Panel\Helper\DbHelper;
use Helio\Panel\Job\AbstractExecute;
use Helio\Panel\Job\DispatchConfig;
use Helio\Panel\Model\Task;
use Helio\Panel\Task\TaskStatus;
use Helio\Panel\Utility\ExecUtility;
use Helio\Panel\Utility\ServerUtility;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Execute extends AbstractExecute
{

    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return bool
     */
    public function create(array $params, RequestInterface $request, ResponseInterface $response = null): bool
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
        App::getApp()->getContainer()['dbHelper']->persist($this->task);
        App::getApp()->getContainer()['dbHelper']->flush();

        $inConfig = json_decode(trim((string)$request->getBody()), true) ?? [];
        $idf = \array_key_exists('idf', $inConfig) ? $inConfig['idf'] : \array_key_exists('idf', $params) ? $params['idf'] : ExecUtility::getExecUrl('work/getidfdata', $this->task, $this->job);
        $epw = \array_key_exists('epw', $inConfig) ? $inConfig['epw'] : \array_key_exists('epw', $params) ? $params['epw'] : ExecUtility::getExecUrl('work/getwetherdata', $this->task, $this->job);

        $outConfig = [
            'idf' => $idf,
            'idf_sha1' => ServerUtility::getHashOfString($idf),
            'epw' => $epw,
            'epw_sha1' => ServerUtility::getHashOfString($epw),
            'report_url' => $params['report_url'] ?? ''
        ];

        if (\array_key_exists('run_id', $params)) {
            $this->task->setName($params['run_id']);
        }

        $this->task->setStatus(TaskStatus::READY)
            ->setConfig(json_encode($outConfig, JSON_UNESCAPED_SLASHES));

        App::getApp()->getContainer()['dbHelper']->persist($this->task);
        App::getApp()->getContainer()['dbHelper']->persist($this->job);
        App::getApp()->getContainer()['dbHelper']->flush();

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
        $tasks = App::getApp()->getContainer()['dbHelper']->getRepository(Task::class)->findBy(['job' => $this->job, 'status' => TaskStatus::READY], ['priority' => 'ASC', 'created' => 'ASC'], 5);
        /** @var Task $task */
        foreach ($tasks as $task) {
            try {
                /** @var Task $lockedTask */
                $lockedTask = App::getApp()->getContainer()['dbHelper']->getRepository(Task::class)->find($task->getId(), LockMode::OPTIMISTIC, $task->getVersion());
                $lockedTask->setStatus(TaskStatus::RUNNING);
                App::getApp()->getContainer()['dbHelper']->flush();
                return $response->withJson(array_merge(json_decode($lockedTask->getConfig(), true), [
                    'id' => (string)$lockedTask->getId(),
                    'report' => ExecUtility::getExecUrl('work/submitresult'),
                    'upload' => ExecUtility::getExecUrl('upload'),
                ]), null, JSON_UNESCAPED_SLASHES);
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
        return ExecUtility::downloadFile(__DIR__ . '/example.epw', $response);
    }


    /**
     * @param array $params
     * @param Response $response
     * @return ResponseInterface
     */
    public function getidfdata(array $params, Response $response): ResponseInterface
    {
        return ExecUtility::downloadFile(__DIR__ . '/example.idf', $response);
    }


    /**
     * @param array $params
     * @param Response $response
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function submitresult(array $params, Response $response, RequestInterface $request): ResponseInterface
    {
        if ($this->task && \array_key_exists('success', $params) && $params['success']) {
            /** @var Task $task */
            $this->task->setStatus(TaskStatus::DONE);
            $this->task->setStats((string)$request->getBody());
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
        return (new DispatchConfig())->setImage('gitlab.idling.host:4567/helio/runner/ep85:latest')->setEnvVariables([
            'HELIO_JOBID' => $this->job->getId(),
            'HELIO_TOKEN' => $this->job->getToken(),
            'HELIO_URL' => ServerUtility::getBaseUrl()
        ]);
    }
}