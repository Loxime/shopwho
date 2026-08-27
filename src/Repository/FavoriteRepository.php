<?php

namespace App\Repository;

use App\Entity\Favorite;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct(
            $registry,
            Favorite::class
        );
    }

    public function findOneForUserAndProduct(
        User $user,
        Product $product
    ): ?Favorite {
        return $this->findOneBy([
            'user' => $user,
            'product' => $product,
        ]);
    }

    public function isFavorite(
        User $user,
        Product $product
    ): bool {
        return $this->findOneForUserAndProduct(
            $user,
            $product
        ) !== null;
    }

    /**
     * @return list<Favorite>
     */
    public function findForUser(
        User $user
    ): array {
        return $this->createQueryBuilder(
            'favorite'
        )
            ->addSelect('product')
            ->addSelect('category')
            ->join(
                'favorite.product',
                'product'
            )
            ->join(
                'product.category',
                'category'
            )
            ->andWhere(
                'favorite.user = :user'
            )
            ->andWhere(
                'product.isActive = :active'
            )
            ->setParameter(
                'user',
                $user
            )
            ->setParameter(
                'active',
                true
            )
            ->orderBy(
                'favorite.createdAt',
                'DESC'
            )
            ->addOrderBy(
                'favorite.id',
                'DESC'
            )
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<int>
     */
    public function findProductIdsForUser(
        User $user
    ): array {
        $rows = $this
            ->createQueryBuilder('favorite')
            ->select(
                'IDENTITY(favorite.product) AS productId'
            )
            ->andWhere(
                'favorite.user = :user'
            )
            ->setParameter(
                'user',
                $user
            )
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): int =>
                (int) $row['productId'],
            $rows
        );
    }
}
