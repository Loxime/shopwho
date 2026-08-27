<?php

namespace App\Repository;

use App\Entity\SpecialOffer;
use App\Enum\SpecialOfferPlacement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SpecialOfferRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct(
            $registry,
            SpecialOffer::class
        );
    }

    public function findActiveHeaderOffer(
        ?\DateTimeImmutable $now = null
    ): ?SpecialOffer {
        $offers = $this->findActiveForPlacements(
            [
                SpecialOfferPlacement::Header,
                SpecialOfferPlacement::Both,
            ],
            1,
            $now
        );

        return $offers[0] ?? null;
    }

    /**
     * @return list<SpecialOffer>
     */
    public function findActiveHomepageOffers(
        int $limit = 8,
        ?\DateTimeImmutable $now = null
    ): array {
        return $this->findActiveForPlacements(
            [
                SpecialOfferPlacement::Homepage,
                SpecialOfferPlacement::Both,
            ],
            max(
                1,
                min($limit, 8)
            ),
            $now
        );
    }

    /**
     * @param list<SpecialOfferPlacement> $placements
     *
     * @return list<SpecialOffer>
     */
    private function findActiveForPlacements(
        array $placements,
        int $limit,
        ?\DateTimeImmutable $now = null
    ): array {
        $now ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('offer')
            ->andWhere(
                'offer.isActive = :active'
            )
            ->andWhere(
                'offer.placement IN (:placements)'
            )
            ->andWhere(
                'offer.startsAt IS NULL
                OR offer.startsAt <= :now'
            )
            ->andWhere(
                'offer.endsAt IS NULL
                OR offer.endsAt >= :now'
            )
            ->setParameter('active', true)
            ->setParameter(
                'placements',
                $placements
            )
            ->setParameter('now', $now)
            ->orderBy(
                'offer.priority',
                'DESC'
            )
            ->addOrderBy(
                'offer.updatedAt',
                'DESC'
            )
            ->addOrderBy(
                'offer.id',
                'DESC'
            )
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
