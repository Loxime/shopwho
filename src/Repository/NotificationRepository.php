<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class NotificationRepository
    extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct(
            $registry,
            Notification::class
        );
    }

    /**
     * @return list<Notification>
     */
    public function findForUser(
        User $user,
        int $limit = 50
    ): array {
        return $this
            ->createQueryBuilder(
                'notification'
            )
            ->andWhere(
                'notification.user = :user'
            )
            ->setParameter(
                'user',
                $user
            )
            ->orderBy(
                'notification.createdAt',
                'DESC'
            )
            ->addOrderBy(
                'notification.id',
                'DESC'
            )
            ->setMaxResults(
                max(
                    1,
                    min($limit, 100)
                )
            )
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForUser(
        User $user
    ): int {
        return (int) $this
            ->createQueryBuilder(
                'notification'
            )
            ->select(
                'COUNT(notification.id)'
            )
            ->andWhere(
                'notification.user = :user'
            )
            ->andWhere(
                'notification.readAt IS NULL'
            )
            ->setParameter(
                'user',
                $user
            )
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Notification>
     */
    public function findUnreadForUser(
        User $user
    ): array {
        return $this
            ->createQueryBuilder(
                'notification'
            )
            ->andWhere(
                'notification.user = :user'
            )
            ->andWhere(
                'notification.readAt IS NULL'
            )
            ->setParameter(
                'user',
                $user
            )
            ->orderBy(
                'notification.createdAt',
                'DESC'
            )
            ->addOrderBy(
                'notification.id',
                'DESC'
            )
            ->getQuery()
            ->getResult();
    }
}
