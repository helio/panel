<?php
namespace Helio\Panel\Job;

use Helio\Panel\Job\JobInterface;
use Helio\Panel\Model\Job;

class JobFactory {

    /** @var array<JobInterface> */
    protected static $instances = [];

    /**
     * @param Job $job
     *
     * @return JobInterface
     */
    public static function getInstanceOfJob(Job $job): JobInterface {

        if (!array_key_exists($job->getId(), self::$instances)) {
            $type = ucfirst(strtolower($job->getType()));
            $className = "\\Helio\\Panel\\Job\\$type";
            self::$instances[$job->getId()] = new $className($job);

        }

        return self::$instances[$job->getId()];
    }
}