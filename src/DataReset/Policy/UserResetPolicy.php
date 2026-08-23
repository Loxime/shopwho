<?php

namespace App\DataReset\Policy;

use App\Entity\User;
use App\Enum\DataOrigin;
use App\Entity\Order;
use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UserResetPolicy
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function decide(User $user): ResetDecision
    {
        if (DataOrigin::Imported !== $user->getDataOrigin()) {
            return new ResetDecision(false, 'native');
        }
        if (($count = $this->count(Order::class, 'user', $user)) > 0) {
            return new ResetDecision(false, 'has_orders', $count);
        }
        if (($count = $this->count(Review::class, 'user', $user)) > 0) {
            return new ResetDecision(false, 'has_reviews', $count);
        }

        return new ResetDecision(true);
    }

    private function count(string $class, string $field, User $user): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(entity.id)')->from($class, 'entity')
            ->where(sprintf('entity.%s = :user', $field))->setParameter('user', $user)->getQuery()->getSingleScalarResult();
    }
}
