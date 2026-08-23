<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /** @return list<Review> */
    public function findForProduct(Product $product): array
    {
        return $this->createQueryBuilder('review')
            ->addSelect('user')
            ->join('review.user', 'user')
            ->andWhere('review.product = :product')
            ->setParameter('product', $product)
            ->orderBy('review.createdAt', 'DESC')
            ->addOrderBy('review.id', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return list<Review> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('review')
            ->addSelect('product')
            ->join('review.product', 'product')
            ->andWhere('review.user = :user')
            ->setParameter('user', $user)
            ->orderBy('review.createdAt', 'DESC')
            ->addOrderBy('review.id', 'DESC')
            ->getQuery()->getResult();
    }

    public function findOneByUserAndProduct(User $user, Product $product): ?Review
    {
        return $this->findOneBy(['user' => $user, 'product' => $product]);
    }

    /** @return array{average: float|null, count: int} */
    public function getProductRatingStats(Product $product): array
    {
        $result = $this->createQueryBuilder('review')
            ->select('AVG(review.rating) AS average', 'COUNT(review.id) AS reviewCount')
            ->andWhere('review.product = :product')
            ->setParameter('product', $product)
            ->getQuery()->getSingleResult();

        return [
            'average' => null === $result['average'] ? null : (float) $result['average'],
            'count' => (int) $result['reviewCount'],
        ];
    }
}
