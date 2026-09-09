<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct(
            $registry,
            Article::class
        );
    }

    /**
     * @return list<Article>
     */
    public function findPublished(
        int $limit = 20
    ): array {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('article')
            ->andWhere(
                'article.isPublished = :published'
            )
            ->andWhere(
                'article.publishedAt IS NULL '
                .'OR article.publishedAt <= :now'
            )
            ->setParameter(
                'published',
                true
            )
            ->setParameter(
                'now',
                $now
            )
            ->orderBy(
                'article.publishedAt',
                'DESC'
            )
            ->addOrderBy(
                'article.createdAt',
                'DESC'
            )
            ->setMaxResults(
                max(1, $limit)
            )
            ->getQuery()
            ->getResult();
    }

    public function findPublishedBySlug(
        string $slug
    ): ?Article {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('article')
            ->andWhere(
                'article.slug = :slug'
            )
            ->andWhere(
                'article.isPublished = :published'
            )
            ->andWhere(
                'article.publishedAt IS NULL '
                .'OR article.publishedAt <= :now'
            )
            ->setParameter(
                'slug',
                $slug
            )
            ->setParameter(
                'published',
                true
            )
            ->setParameter(
                'now',
                $now
            )
            ->getQuery()
            ->getOneOrNullResult();
    }
}
