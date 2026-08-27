<?php

namespace App\Tests\Unit;

use App\Analytics\AnalyticsFunnel;
use App\Analytics\ProductAnalytics;
use PHPUnit\Framework\TestCase;

final class AnalyticsTest extends TestCase
{
    public function testFunnelRatesAreCalculated(): void
    {
        $funnel = new AnalyticsFunnel(
            3,
            2,
            1,
            1
        );

        self::assertSame(
            66.67,
            $funnel->viewToCartRate()
        );

        self::assertSame(
            50.0,
            $funnel->cartToCheckoutRate()
        );

        self::assertSame(
            100.0,
            $funnel->checkoutToPurchaseRate()
        );

        self::assertSame(
            33.33,
            $funnel->viewToPurchaseRate()
        );

        self::assertSame(
            33.33,
            $funnel->checkoutShareOfViews()
        );
    }

    public function testFunnelRatesAreZeroWithoutBase(): void
    {
        $funnel = new AnalyticsFunnel(
            0,
            0,
            0,
            0
        );

        self::assertSame(
            0.0,
            $funnel->viewToCartRate()
        );

        self::assertSame(
            0.0,
            $funnel->checkoutToPurchaseRate()
        );

        self::assertSame(
            0.0,
            $funnel->viewToPurchaseRate()
        );
    }

    public function testProductCartRateIsCalculated(): void
    {
        $product = new ProductAnalytics(
            1,
            'Produit test',
            'produit-test',
            8,
            2,
            1,
            3
        );

        self::assertSame(
            25.0,
            $product->cartRate()
        );
    }

    public function testProductCartRateIsZeroWithoutViews(): void
    {
        $product = new ProductAnalytics(
            1,
            'Produit test',
            'produit-test',
            0,
            4,
            0,
            0
        );

        self::assertSame(
            0.0,
            $product->cartRate()
        );
    }
}
