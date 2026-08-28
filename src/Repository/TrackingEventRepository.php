<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\TrackingEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TrackingEventRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct(
            $registry,
            TrackingEvent::class
        );
    }

    /**
     * @return list<array{
     *     productId: int,
     *     score: int
     * }>
     */
    public function findFrequentlyViewedProductScores(
        User $user,
        int $days = 30,
        int $limit = 20
    ): array {
        $since = new \DateTimeImmutable(
            sprintf('-%d days', max(1, $days))
        );

        $rows = $this->createQueryBuilder('event')
            ->select(
                'event.productId AS productId',
                'COUNT(event.id) AS score'
            )
            ->andWhere('event.user = :user')
            ->andWhere(
                'event.eventType = :eventType'
            )
            ->andWhere(
                'event.productId IS NOT NULL'
            )
            ->andWhere(
                'event.occurredAt >= :since'
            )
            ->setParameter('user', $user)
            ->setParameter(
                'eventType',
                'PRODUCT_VIEW'
            )
            ->setParameter('since', $since)
            ->groupBy('event.productId')
            ->orderBy('score', 'DESC')
            ->addOrderBy(
                'MAX(event.occurredAt)',
                'DESC'
            )
            ->setMaxResults(
                max(1, $limit)
            )
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): array => [
                'productId' =>
                    (int) $row['productId'],
                'score' =>
                    (int) $row['score'],
            ],
            $rows
        );
    }
}
