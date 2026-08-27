<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use App\Review\AdminReviewFilter;
use App\Review\AdminReviewPage;
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

    /**
     * @param list<int> $productIds
     *
     * @return array<int, array{average: float, count: int}>
     */
    public function getRatingStatsByProductIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('review')
            ->select(
                'IDENTITY(review.product) AS productId',
                'AVG(review.rating) AS average',
                'COUNT(review.id) AS reviewCount'
            )
            ->andWhere('IDENTITY(review.product) IN (:productIds)')
            ->setParameter('productIds', $productIds)
            ->groupBy('review.product')
            ->getQuery()
            ->getArrayResult();

        $stats = [];

        foreach ($rows as $row) {
            $stats[(int) $row['productId']] = [
                'average' => (float) $row['average'],
                'count' => (int) $row['reviewCount'],
            ];
        }

        return $stats;
    }

    public function searchForAdmin(
        AdminReviewFilter $filter
    ): AdminReviewPage {
        $qb = $this->createQueryBuilder(
            'review'
        )
            ->addSelect('user')
            ->addSelect('product')
            ->join(
                'review.user',
                'user'
            )
            ->join(
                'review.product',
                'product'
            );

        if (null !== $filter->search) {
            $qb
                ->andWhere(
                    $qb->expr()->orX(
                        'LOWER(product.name) LIKE :search',
                        'LOWER(product.slug) LIKE :search',
                        'LOWER(user.email) LIKE :search',
                        'LOWER(user.firstName) LIKE :search',
                        'LOWER(user.lastName) LIKE :search',
                        'LOWER(review.comment) LIKE :search'
                    )
                )
                ->setParameter(
                    'search',
                    '%'.mb_strtolower(
                        $filter->search
                    ).'%'
                );
        }

        if (null !== $filter->rating) {
            $qb
                ->andWhere(
                    'review.rating = :rating'
                )
                ->setParameter(
                    'rating',
                    $filter->rating
                );
        }

        if (
            AdminReviewFilter::SOURCE_NATIVE
            === $filter->source
        ) {
            $qb->andWhere(
                'review.externalRef IS NULL'
            );
        }

        if (
            AdminReviewFilter::SOURCE_IMPORTED
            === $filter->source
        ) {
            $qb->andWhere(
                'review.externalRef IS NOT NULL'
            );
        }

        $countQb = clone $qb;

        $total = (int) $countQb
            ->select(
                'COUNT(review.id)'
            )
            ->resetDQLPart(
                'orderBy'
            )
            ->getQuery()
            ->getSingleScalarResult();

        $pageCount = max(
            1,
            (int) ceil(
                $total / $filter->perPage
            )
        );

        $page = min(
            $filter->page,
            $pageCount
        );

        $items = $qb
            ->orderBy(
                'review.createdAt',
                'DESC'
            )
            ->addOrderBy(
                'review.id',
                'DESC'
            )
            ->setFirstResult(
                ($page - 1)
                * $filter->perPage
            )
            ->setMaxResults(
                $filter->perPage
            )
            ->getQuery()
            ->getResult();

        return new AdminReviewPage(
            $items,
            $total,
            $page,
            $filter->perPage
        );
    }
}
