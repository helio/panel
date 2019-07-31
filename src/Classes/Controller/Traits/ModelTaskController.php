<?php

namespace Helio\Panel\Controller\Traits;

use \Exception;
use Helio\Panel\App;
use Helio\Panel\Model\Task;
use Helio\Panel\Task\TaskStatus;

/**
 * Trait ModelTaskController
 * @package Helio\Panel\Controller\Traits
 */
trait ModelTaskController
{
    use ModelJobController;

    /**
     * @var Task
     */
    protected $task;


    /**
     * @return bool
     * @throws Exception
     */
    public function setupTask(): bool
    {
        $this->setupParams();
        $this->setupJob();
        $taskId = filter_var($this->params['taskid'] ?? ($this->idAlias === 'taskid' ? $this->params['id'] : 0), FILTER_SANITIZE_NUMBER_INT);
        if ($taskId > 0) {
            $this->task = App::getDbHelper()->getRepository(Task::class)->find($taskId);
            return true;
        }

        $this->task = (new Task())->setStatus(TaskStatus::UNKNOWN)->setJob($this->job)->setCreated()->setName('___NEW');
        return true;
    }


    /**
     * @return bool
     * @throws Exception
     */
    public function validateTaskIsSet(): bool
    {
        if ($this->task) {
            if ($this->task->getName() !== '___NEW') {
                $this->persistTask();
            }
            return true;
        }
        return false;
    }


    /**
     * Persist
     * @throws Exception
     */
    protected function persistTask(): void
    {
        if ($this->task) {
            $this->task->setLatestAction();
            App::getDbHelper()->persist($this->task);
            App::getDbHelper()->flush($this->task);
        }
    }
}