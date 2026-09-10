<?php

namespace App\Repository;

use App\Entity\Experiment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ExperimentRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct(
            $registry,
            Experiment::class
        );
    }

    public function findByKey(
        string $key
    ): ?Experiment {
        return $this
            ->createQueryBuilder('experiment')
            ->addSelect('variants')
            ->leftJoin(
                'experiment.variants',
                'variants'
            )
            ->andWhere(
                'experiment.key = :key'
            )
            ->setParameter(
                'key',
                $key
            )
            ->getQuery()
            ->getOneOrNullResult();
    }
}
