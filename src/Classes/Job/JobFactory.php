<?php

namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;

class JobFactory
{

    /**
     * @param Job $job
     * @param Task|null $task
     * @return JobInterface
     */
    public static function getInstanceOfJob(Job $job, Task $task = null): JobInterface
    {
        $type = ucfirst(strtolower($job->getType()));
        $className = "\\Helio\\Panel\\Job\\$type\\Execute";
        return new $className($job, $task);
    }

    /**
     * @param Job $job
     * @param Task|null $task
     * @return DispatchableInterface
     */
    public static function getDispatchConfigOfJob(Job $job, Task $task = null): DispatchableInterface
    {
        $type = ucfirst(strtolower($job->getType()));
        $className = "\\Helio\\Panel\\Job\\$type\\Execute";
        return new $className($job, $task);
    }
}