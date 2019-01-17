<?php

namespace Helio\Panel\Controller\Traits;

use Helio\Panel\Model\Instance;

/**
 * Trait StatisticsController
 * @package Helio\Panel\Controller\Traits
 *
 */
trait StatisticsController
{
    use AuthenticatedController;

    protected function statServerByRegion(): array {
        $query = $this->dbHelper->getRepository(Instance::class)->createQueryBuilder('c');
        return $query
                ->select('c.region, COUNT(c.id) as cnt')
            ->where('c.owner = ' . $this->user->getId())
            ->groupBy('c.region')
            ->getQuery()->getArrayResult();
    }
}