<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** @return Product[] */
    public function findCatalog(?string $query = null, ?string $category = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC');

        if ($query) {
            $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.description) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($query)).'%');
        }

        if ($category) {
            $qb->andWhere('c.slug = :category')->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Product>
     */
    public function findPopularOrderedProducts(
        int $days = 30,
        int $limit = 8
    ): array {
        $since = new \DateTimeImmutable(
            sprintf('-%d days', max(1, $days))
        );

        return $this->createQueryBuilder('product')
            ->join(
                OrderItem::class,
                'item',
                'WITH',
                'item.product = product'
            )
            ->join(
                'item.order',
                'customerOrder'
            )
            ->addSelect('category')
            ->join(
                'product.category',
                'category'
            )
            ->andWhere(
                'product.isActive = :active'
            )
            ->andWhere(
                'customerOrder.status IN (:statuses)'
            )
            ->andWhere(
                'customerOrder.orderedAt >= :since'
            )
            ->setParameter('active', true)
            ->setParameter(
                'statuses',
                [
                    Order::STATUS_SIMULATED_COMPLETED,
                    Order::STATUS_IMPORTED_COMPLETED,
                ]
            )
            ->setParameter('since', $since)
            ->groupBy('product.id')
            ->addGroupBy('category.id')
            ->orderBy(
                'SUM(item.quantity)',
                'DESC'
            )
            ->addOrderBy(
                'MAX(customerOrder.orderedAt)',
                'DESC'
            )
            ->setMaxResults(
                max(1, $limit)
            )
            ->getQuery()
            ->getResult();
    }
    
    /**
     * @return list<Product>
     */
    public function findTopRatedProducts(
        int $limit = 8
    ): array {
        return $this->createQueryBuilder('product')
            ->addSelect('category')
            ->join(
                'product.category',
                'category'
            )
            ->join(
                'product.reviews',
                'review'
            )
            ->andWhere(
                'product.isActive = :active'
            )
            ->setParameter('active', true)
            ->groupBy('product.id')
            ->addGroupBy('category.id')
            ->orderBy(
                'AVG(review.rating)',
                'DESC'
            )
            ->addOrderBy(
                'COUNT(review.id)',
                'DESC'
            )
            ->addOrderBy(
                'product.createdAt',
                'DESC'
            )
            ->setMaxResults(
                max(1, $limit)
            )
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Product>
     */
    public function findActiveByIds(
        array $ids
    ): array {
        if ($ids === []) {
            return [];
        }

        $products = $this->createQueryBuilder(
            'product'
        )
            ->addSelect('category')
            ->join(
                'product.category',
                'category'
            )
            ->andWhere(
                'product.id IN (:ids)'
            )
            ->andWhere(
                'product.isActive = :active'
            )
            ->setParameter('ids', $ids)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $productsById = [];

        foreach ($products as $product) {
            $productsById[
                $product->getId()
            ] = $product;
        }

        $ordered = [];

        foreach ($ids as $id) {
            if (
                isset(
                    $productsById[$id]
                )
            ) {
                $ordered[] =
                    $productsById[$id];
            }
        }

        return $ordered;
    }
}
