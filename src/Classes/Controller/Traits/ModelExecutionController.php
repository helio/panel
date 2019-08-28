<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
use Exception;
use Helio\Panel\App;
use Helio\Panel\Model\Execution;
use Helio\Panel\Execution\ExecutionStatus;

/**
 * Trait ModelExecutionController.
 */
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
     * @return bool
     *
     * @throws Exception
     */
    public function setupExecution(RouteInfo $route): bool
    {
        $this->setupParams($route);
        $this->setupJob($route);
        $executionId = filter_var($this->params['executionid'] ?? ('executionid' === $this->getIdAlias() ? (array_key_exists('id', $this->params) ? $this->params['id'] : 0) : 0), FILTER_SANITIZE_NUMBER_INT);
        if ($executionId > 0) {
            // TODO(michael): probably resolving it via $this->job (via the association) would be better.
            $this->execution = App::getDbHelper()->getRepository(Execution::class)->findOneBy(['id' => $executionId, 'job' => $this->job->getId()]);

            return true;
        }

        $this->execution = (new Execution())
            ->setStatus(ExecutionStatus::UNKNOWN)
            ->setName('___NEW');

        return true;
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    public function validateExecutionIsSet(): bool
    {
        if ($this->execution) {
            if ('___NEW' !== $this->execution->getName()) {
                $this->persistExecution();
            }

            return true;
        }

        return false;
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
