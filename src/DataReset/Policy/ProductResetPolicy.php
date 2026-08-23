<?php

namespace App\DataReset\Policy;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Review;
use App\Enum\DataOrigin;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProductResetPolicy
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function decide(Product $product): ResetDecision
    {
        if (DataOrigin::Imported !== $product->getDataOrigin()) {
            return new ResetDecision(false, 'native');
        }
        $count = (int) $this->em->createQueryBuilder()->select('COUNT(review.id)')->from(Review::class, 'review')
            ->where('review.product = :product')->setParameter('product', $product)->getQuery()->getSingleScalarResult();
        if ($count > 0) {
            return new ResetDecision(false, 'has_reviews', $count);
        }
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(item.id)')
            ->from(OrderItem::class, 'item')
            ->where('item.product = :product')
            ->orWhere('item.productIdSnapshot = :productId')
            ->orWhere('item.productExternalRefSnapshot = :externalRef')
            ->setParameter('product', $product)
            ->setParameter('productId', $product->getId())
            ->setParameter('externalRef', $product->getExternalRef())
            ->getQuery()->getSingleScalarResult();
        if ($count > 0) {
            return new ResetDecision(false, 'used_in_order_history', $count);
        }

        return new ResetDecision(true);
    }
}
