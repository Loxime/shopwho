<?php

namespace App\Repository;

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
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC');

        if ($query) {
            $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.description) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($query)).'%');
        }

        if ($category) {
            $qb->andWhere('p.category = :category')->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return string[] */
    public function findActiveCategories(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.category AS category')
            ->andWhere('p.isActive = true')
            ->orderBy('p.category', 'ASC')
            ->getQuery()->getArrayResult();

        return array_column($rows, 'category');
    }
}
