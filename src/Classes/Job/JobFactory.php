<?php
namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;
use Helio\Panel\Model\Task;

class JobFactory {

    /** @var array<JobInterface> */
    protected static $instances = [];

    /**
     * @param Job $job
     * @param Task|null $task
     * @return JobInterface
     */
    public static function getInstanceOfJob(Job $job, Task $task = null): JobInterface {

        if (!array_key_exists($job->getId(), self::$instances)) {
            $type = ucfirst(strtolower($job->getType()));
            $className = "\\Helio\\Panel\\Job\\$type\\Execute";
            self::$instances[$job->getId()] = new $className($job, $task);

        }

        return self::$instances[$job->getId()];
    }

    /**
     * @param Job $job
     * @param Task|null $task
     * @return DispatchableInterface
     */
    public static function getDispatchConfigOfJob(Job $job, Task $task = null): DispatchableInterface {

        if (!array_key_exists($job->getId(), self::$instances)) {
            $type = ucfirst(strtolower($job->getType()));
            $className = "\\Helio\\Panel\\Job\\$type\\Execute";
            self::$instances[$job->getId()] = new $className($job, $task);

        }

        return self::$instances[$job->getId()];
    }
}