<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
use Exception;
use Helio\Panel\App;
use Helio\Panel\Exception\HttpException;
use Helio\Panel\Model\Execution;
use Helio\Panel\Execution\ExecutionStatus;
use Slim\Http\StatusCode;

trait ModelExecutionController
{
    use ModelJobController;

    /**
     * @var Execution
     */
    protected $execution;

    /**
     * @param RouteInfo $route
     *
     * @throws Exception
     */
    public function setupExecution(RouteInfo $route): void
    {
        $this->setupParams($route);
        $this->setupJob($route);

        if (!$this->job || null === $this->job->getId()) {
            throw new HttpException(StatusCode::HTTP_NOT_FOUND, 'Job not found');
        }

        $executionId = filter_var($this->params['executionid'] ?? ('executionid' === $this->getIdAlias() ? (array_key_exists('id', $this->params) ? $this->params['id'] : 0) : 0), FILTER_SANITIZE_NUMBER_INT);
        if ($executionId > 0) {
            // TODO(michael): probably resolving it via $this->job (via the association) would be better.
            $this->execution = App::getDbHelper()->getRepository(Execution::class)->findOneBy(['id' => $executionId, 'job' => $this->job->getId()]);

            return;
        }

        $this->execution = (new Execution())
            ->setStatus(ExecutionStatus::UNKNOWN)
            ->setName('___NEW');

        return;
    }

    /**
     * @throws Exception
     */
    public function validateExecutionIsSet(): void
    {
        if ($this->execution) {
            if ('___NEW' !== $this->execution->getName()) {
                $this->persistExecution();
            }

            return;
        }

        throw new HttpException(StatusCode::HTTP_NOT_FOUND, 'No execution found');
    }

    /**
     * Persist.
     *
     * @throws Exception
     */
    protected function persistExecution(): void
    {
        if ($this->execution) {
            $this->execution->setLatestAction();
            App::getDbHelper()->persist($this->execution);
            App::getDbHelper()->flush($this->execution);
        }
    }
}
