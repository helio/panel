<?php

namespace Helio\Panel\Job\Vfdocker;

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
     * @param ResponseInterface $response
     * @return bool
     * @throws \Exception
     */
    public function run(array $params, RequestInterface $request, ResponseInterface $response): bool
    {
        $this->task = (new Task())->setJob($this->job)->setCreated(new \DateTime('now', ServerUtility::getTimezoneObject()))->setConfig((string)$request->getBody());
        App::getApp()->getContainer()['dbHelper']->persist($this->task);
        App::getApp()->getContainer()['dbHelper']->flush();
        return true;
    }


    /**
     * @return DispatchConfig
     */
    public function getDispatchConfig(): DispatchConfig
    {
        return (new DispatchConfig())
            ->setFixedReplicaCount(1)// enforce call of dispatch command on every new task
            ->setImage($this->job->getConfig('container'))
            ->setEnvVariables(
                array_merge($this->job->getConfig('env', []), [
                    'HELIO_JOBID' => $this->job->getId(),
                    'HELIO_TOKEN' => $this->job->getToken(),
                    'REPORT_URL' => ServerUtility::getBaseUrl() . ExecUtility::getExecUrl('work/submitresult', $this->task, $this->job)
                ])
            )
            ->setRegistry($this->job->getConfig('registry', []));
    }


    /**
     * @param array $params
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     *
     * @return bool
     */
    public function create(array $params, RequestInterface $request, ResponseInterface $response = null): bool
    {
        $this->job->setConfig((string)$request->getBody());
        return true;
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
}