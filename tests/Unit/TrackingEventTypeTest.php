<?php

namespace App\Tests\Unit;

use App\Enum\TrackingEventType;
use PHPUnit\Framework\TestCase;

final class TrackingEventTypeTest extends TestCase
{
    public function testTrackingContractContainsExpectedEvents(): void
    {
        self::assertSame(
            [
                'PAGE_VIEW',
                'SEARCH',
                'CATEGORY_VIEW',
                'PRODUCT_VIEW',
                'PRODUCT_CARD_IMPRESSION',
                'PRODUCT_CARD_CLICK',
                'CART_VIEW',
                'ADD_TO_CART',
                'CART_QUANTITY_CHANGED',
                'REMOVE_FROM_CART',
                'CHECKOUT_STARTED',
                'PURCHASE',
                'FAVORITE_ADDED',
                'FAVORITE_REMOVED',
                'RECOMMENDATION_IMPRESSION',
                'RECOMMENDATION_CLICK',
                'SPECIAL_OFFER_IMPRESSION',
                'SPECIAL_OFFER_CLICK',
            ],
            TrackingEventType::values()
        );
    }

    public function testUnknownEventIsRejectedByEnum(): void
    {
        self::assertNull(
            TrackingEventType::tryFrom(
                'UNKNOWN_EVENT'
            )
        );
    }

    public function testEventValuesAreUnique(): void
    {
        $values =
            TrackingEventType::values();

        self::assertCount(
            count($values),
            array_unique($values)
        );
    }
}
