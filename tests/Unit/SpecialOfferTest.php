<?php

namespace App\Tests\Unit;

use App\Entity\SpecialOffer;
use App\Enum\SpecialOfferPlacement;
use PHPUnit\Framework\TestCase;

final class SpecialOfferTest extends TestCase
{
    public function testHeaderPlacementCapabilities(): void
    {
        self::assertTrue(
            SpecialOfferPlacement::Header
                ->includesHeader()
        );

        self::assertFalse(
            SpecialOfferPlacement::Header
                ->includesHomepage()
        );
    }

    public function testHomepagePlacementCapabilities(): void
    {
        self::assertFalse(
            SpecialOfferPlacement::Homepage
                ->includesHeader()
        );

        self::assertTrue(
            SpecialOfferPlacement::Homepage
                ->includesHomepage()
        );
    }

    public function testBothPlacementCapabilities(): void
    {
        self::assertTrue(
            SpecialOfferPlacement::Both
                ->includesHeader()
        );

        self::assertTrue(
            SpecialOfferPlacement::Both
                ->includesHomepage()
        );
    }

    public function testActiveOfferWithoutDatesIsCurrentlyActive(): void
    {
        $offer = (new SpecialOffer())
            ->setTitle('Test')
            ->setContent('Contenu')
            ->setIsActive(true);

        self::assertTrue(
            $offer->isCurrentlyActive(
                new \DateTimeImmutable(
                    '2026-08-27 12:00:00'
                )
            )
        );
    }

    public function testInactiveFutureAndExpiredOffersAreNotCurrentlyActive(): void
    {
        $now = new \DateTimeImmutable(
            '2026-08-27 12:00:00'
        );

        $inactive = (new SpecialOffer())
            ->setTitle('Inactive')
            ->setContent('Test')
            ->setIsActive(false);

        self::assertFalse(
            $inactive->isCurrentlyActive($now)
        );

        $future = (new SpecialOffer())
            ->setTitle('Future')
            ->setContent('Test')
            ->setStartsAt(
                $now->modify('+1 hour')
            );

        self::assertFalse(
            $future->isCurrentlyActive($now)
        );

        $expired = (new SpecialOffer())
            ->setTitle('Expired')
            ->setContent('Test')
            ->setEndsAt(
                $now->modify('-1 second')
            );

        self::assertFalse(
            $expired->isCurrentlyActive($now)
        );
    }

    public function testDateBoundariesAreInclusive(): void
    {
        $now = new \DateTimeImmutable(
            '2026-08-27 12:00:00'
        );

        $offer = (new SpecialOffer())
            ->setTitle('Boundary')
            ->setContent('Test')
            ->setStartsAt($now)
            ->setEndsAt($now);

        self::assertTrue(
            $offer->isCurrentlyActive($now)
        );
    }
}
