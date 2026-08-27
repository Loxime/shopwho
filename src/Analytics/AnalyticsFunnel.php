<?php

namespace App\Analytics;

final readonly class AnalyticsFunnel
{
    public function __construct(
        public int $productViewSessions,
        public int $cartSessions,
        public int $checkoutSessions,
        public int $purchaseSessions,
    ) {
    }

    public function viewToCartRate(): float
    {
        return $this->rate(
            $this->cartSessions,
            $this->productViewSessions
        );
    }

    public function cartToCheckoutRate(): float
    {
        return $this->rate(
            $this->checkoutSessions,
            $this->cartSessions
        );
    }

    public function checkoutToPurchaseRate(): float
    {
        return $this->rate(
            $this->purchaseSessions,
            $this->checkoutSessions
        );
    }

    public function viewToPurchaseRate(): float
    {
        return $this->rate(
            $this->purchaseSessions,
            $this->productViewSessions
        );
    }

    public function checkoutShareOfViews(): float
    {
        return $this->rate(
            $this->checkoutSessions,
            $this->productViewSessions
        );
    }

    private function rate(
        int $value,
        int $base
    ): float {
        if ($base === 0) {
            return 0.0;
        }

        return round(
            ($value / $base) * 100,
            2
        );
    }
}
