<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /** @return list<Order> */
    public function findRecentForUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('customerOrder')
            ->andWhere('customerOrder.user = :user')
            ->setParameter('user', $user)
            ->orderBy('customerOrder.orderedAt', 'DESC')
            ->addOrderBy('customerOrder.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @return list<Order> */
    public function findAllForUser(User $user): array
    {
        return $this->findRecentForUser($user, PHP_INT_MAX);
    }

    public function findOneByReferenceAndUser(string $reference, User $user): ?Order
    {
        return $this->findOneBy(['reference' => $reference, 'user' => $user]);
    }
}
