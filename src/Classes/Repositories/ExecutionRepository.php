<?php

namespace Helio\Panel\Repositories;

use Doctrine\ORM\EntityRepository;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;

class ExecutionRepository extends EntityRepository
{
    /**
     * @param  Job         $job
     * @return Execution[]
     */
    public function findExecutionsToStart(Job $job): array
    {
        return $this->findBy(['job' => $job, 'status' => ExecutionStatus::READY, 'replicas' => 0], ['priority' => 'ASC', 'created' => 'ASC'], 5);
    }
}
