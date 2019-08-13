<?php

namespace Helio\Panel\Job;

use Helio\Panel\Model\Job;
use Helio\Panel\Model\Execution;

class JobFactory
{
    /**
     * @param Job            $job
     * @param Execution|null $execution
     *
     * @return JobInterface
     */
    public static function getInstanceOfJob(Job $job, Execution $execution = null): JobInterface
    {
        $type = ucfirst(strtolower($job->getType()));
        $className = "\\Helio\\Panel\\Job\\$type\\Execute";

        return new $className($job, $execution);
    }

    /**
     * @param Job            $job
     * @param Execution|null $execution
     *
     * @return DispatchableInterface
     */
    public static function getDispatchConfigOfJob(Job $job, Execution $execution = null): DispatchableInterface
    {
        $type = ucfirst(strtolower($job->getType()));
        $className = "\\Helio\\Panel\\Job\\$type\\Execute";

        return new $className($job, $execution);
    }
}
