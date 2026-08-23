<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /** @return Category[] */
    public function findForNavigation(int $limit = 6): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.showInNavigation = true')
            ->orderBy('c.navigationPosition', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Category[] */
    public function findFeatured(): array
    {
        return $this->findBy(['isFeatured' => true], ['navigationPosition' => 'ASC', 'name' => 'ASC']);
    }
}
