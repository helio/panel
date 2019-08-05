<?php

namespace Helio\Panel\Controller\Traits;

use Ergy\Slim\Annotations\RouteInfo;
use \Exception;
use Helio\Panel\App;
use Helio\Panel\Model\Execution;
use Helio\Panel\Execution\ExecutionStatus;

/**
 * Trait ModelExecutionController
 * @package Helio\Panel\Controller\Traits
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
     * @return bool
     * @throws Exception
     */
    public function setupExecution(RouteInfo $route): bool
    {
        $this->setupParams($route);
        $this->setupJob($route);
        $executionId = filter_var($this->params['executionid'] ?? ($this->getIdAlias() === 'executionid' ? (array_key_exists('id', $this->params) ? $this->params['id'] : 0) : 0), FILTER_SANITIZE_NUMBER_INT);
        if ($executionId > 0) {
            $this->execution = App::getDbHelper()->getRepository(Execution::class)->find($executionId);
            return true;
        }

        $this->execution = (new Execution())->setStatus(ExecutionStatus::UNKNOWN)->setJob($this->job)->setCreated()->setName('___NEW');
        return true;
    }


    /**
     * @return bool
     * @throws Exception
     */
    public function validateExecutionIsSet(): bool
    {
        if ($this->execution) {
            if ($this->execution->getName() !== '___NEW') {
                $this->persistExecution();
            }
            return true;
        }
        return false;
    }


    /**
     * Persist
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