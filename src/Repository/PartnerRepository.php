<?php

namespace App\Repository;

use App\Entity\Partner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PartnerRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct(
            $registry,
            Partner::class
        );
    }

    /**
     * @return list<Partner>
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder(
            'partner'
        )
            ->andWhere(
                'partner.isActive = :active'
            )
            ->setParameter(
                'active',
                true
            )
            ->orderBy(
                'partner.priority',
                'DESC'
            )
            ->addOrderBy(
                'partner.name',
                'ASC'
            )
            ->getQuery()
            ->getResult();
    }
}
