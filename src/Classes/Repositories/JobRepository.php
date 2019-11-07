<?php

namespace Helio\Panel\Repositories;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Helio\Panel\Job\JobStatus;
use Helio\Panel\Model\Execution;
use Helio\Panel\Model\Job;

class JobRepository extends EntityRepository
{
    public function getExecutionCountHavingReplicas(array $labels): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('count(j.id)')
            ->from(Job::class, 'j')
            ->leftJoin(Execution::class, 'e')
            ->where($qb->expr()->andX(
                $qb->expr()->eq('j.status', JobStatus::READY),
                $qb->expr()->eq('e.replicas', 1)
            ));

        $this->generateLabelsCondition($labels, $qb);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findNextJobInQueue(array $labels, int $skipJobId): ?Job
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('j')
            ->from(Job::class, 'j')
            ->where(
                $qb->expr()->eq('j.status', JobStatus::READY),
                $qb->expr()->neq('j.id', ':skipJobId')
            )
            ->setParameter('skipJobId', $skipJobId)
            ->addOrderBy('j.priority', 'ASC')
            ->addOrderBy('j.created', 'ASC');

        $this->generateLabelsCondition($labels, $qb);

        return $qb->getQuery()->getOneOrNullResult();
    }

    protected function generateLabelsCondition(array $labels, QueryBuilder $qb): void
    {
        $orLabels = [];
        for ($i = 0, $l = count($labels); $i < $l; ++$i) {
            // FIXME(mw): this is damn damn ugly! Make labels a separate table and m:n association with jobs
            $orLabels[] = $qb->expr()->like('CONCAT(\',\', j.labels, \',\')', ':label' . $i);
            $qb->setParameter('label' . $i, '%,' . $labels[$i] . ',%');
        }
        $qb->andWhere($qb->expr()->orX(...$orLabels));
    }
}