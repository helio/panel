<?php

namespace Helio\Panel\Repositories;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Helio\Panel\Execution\ExecutionStatus;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;

class ExecutionRepository extends EntityRepository
{
    /**
     * @param  string[]    $labels
     * @param  int         $limit
     * @return Execution[]
     */
    public function findExecutionsToStart(array $labels, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('e')
            ->join(Job::class, 'j', Join::WITH, 'j.id=e.job')
            ->where(
                $qb->expr()->eq('j.status', JobStatus::READY),
                $qb->expr()->eq('e.status', ExecutionStatus::READY),
                $qb->expr()->eq('e.replicas', 0)
            )
            ->addOrderBy('j.priority', 'ASC')
            ->addOrderBy('e.created', 'ASC')
            ->setMaxResults($limit);

        JobRepository::generateLabelsCondition($labels, $qb);

        return $qb->getQuery()->getResult();
    }
}
