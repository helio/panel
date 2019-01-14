<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Model\Task;
use Helio\Panel\Model\User;

/**
 * Trait TaskController
 * @package Helio\Panel\Controller\Traits
 * @method User getUser()
 * @method bool hasUser()
 */
trait TaskController
{
    use ParametrizedController;

    /**
     * @var Task
     */
    protected $task;


    /**
     * @return bool
     */
    public function setupTask(): bool
    {
        $this->setupParams();
        $taskId = filter_var($this->params['taskid'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
        if ($taskId === 0) {
            return false;
        }
        $this->task = $this->dbHelper->getRepository(Task::class)->find($taskId);
        return true;
    }

    /**
     * Persist
     */
    protected function persistTask(): void
    {
        $this->dbHelper->persist($this->task);
        $this->dbHelper->flush($this->task);
    }
}