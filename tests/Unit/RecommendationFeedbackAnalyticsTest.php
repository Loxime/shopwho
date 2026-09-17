<?php

namespace App\Tests\Unit;

use App\Analytics\RecommendationFeedbackAnalytics;
use PHPUnit\Framework\TestCase;

final class RecommendationFeedbackAnalyticsTest
    extends TestCase
{
    public function testFunnelRatesAreCalculated(): void
    {
        $feedback =
            new RecommendationFeedbackAnalytics(
                6,
                'Souris sans fil',
                'top_rated',
                'candidate',
                10,
                8,
                4,
                2,
            );

        self::assertSame(
            80.0,
            $feedback->ctr()
        );

        self::assertSame(
            50.0,
            $feedback->clickToCartRate()
        );

        self::assertSame(
            50.0,
            $feedback->cartToPurchaseRate()
        );

        self::assertSame(
            20.0,
            $feedback->exposureToPurchaseRate()
        );
    }

    public function testZeroDenominatorsReturnZero(): void
    {
        $feedback =
            new RecommendationFeedbackAnalytics(
                6,
                'Souris sans fil',
                'top_rated',
                null,
                0,
                0,
                0,
                0,
            );

        self::assertSame(
            0.0,
            $feedback->ctr()
        );

        self::assertSame(
            0.0,
            $feedback->clickToCartRate()
        );

        self::assertSame(
            0.0,
            $feedback->cartToPurchaseRate()
        );

        self::assertSame(
            0.0,
            $feedback->exposureToPurchaseRate()
        );
    }
}
