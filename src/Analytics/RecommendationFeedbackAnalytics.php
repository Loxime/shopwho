<?php

namespace App\Analytics;

final readonly class RecommendationFeedbackAnalytics
{
    public function __construct(
        public int $productId,
        public string $productName,
        public string $strategy,
        public ?string $experimentVariant,
        public int $exposedJourneys,
        public int $clickedJourneys,
        public int $cartedJourneys,
        public int $purchasedJourneys,
    ) {
    }

    public function ctr(): float
    {
        return $this->rate(
            $this->clickedJourneys,
            $this->exposedJourneys
        );
    }

    public function clickToCartRate(): float
    {
        return $this->rate(
            $this->cartedJourneys,
            $this->clickedJourneys
        );
    }

    public function cartToPurchaseRate(): float
    {
        return $this->rate(
            $this->purchasedJourneys,
            $this->cartedJourneys
        );
    }

    public function exposureToPurchaseRate(): float
    {
        return $this->rate(
            $this->purchasedJourneys,
            $this->exposedJourneys
        );
    }

    private function rate(
        int $numerator,
        int $denominator
    ): float {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(
            ($numerator / $denominator) * 100,
            2
        );
    }
}
