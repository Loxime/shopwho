<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Entity\Product;
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
        return $this->createQueryBuilder('customerOrder')
            ->andWhere('customerOrder.user = :user')
            ->setParameter('user', $user)
            ->orderBy('customerOrder.orderedAt', 'DESC')
            ->addOrderBy('customerOrder.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByReferenceAndUser(string $reference, User $user): ?Order
    {
        return $this->findOneBy(['reference' => $reference, 'user' => $user]);
    }

    public function hasUserPurchasedProduct(User $user, Product $product): bool
    {
        if (null === $product->getId()) {
            return false;
        }

        return (bool) $this->createQueryBuilder('customerOrder')
            ->select('1')
            ->join('customerOrder.items', 'item')
            ->andWhere('customerOrder.user = :user')
            ->andWhere('customerOrder.status IN (:statuses)')
            ->andWhere('(item.product = :product OR item.productIdSnapshot = :productId)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [Order::STATUS_SIMULATED_COMPLETED, Order::STATUS_IMPORTED_COMPLETED])
            ->setParameter('product', $product)
            ->setParameter('productId', $product->getId())
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
