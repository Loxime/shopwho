<?php

namespace App\Analytics;

final readonly class InteractionAnalytics
{
    public function __construct(
        public int $productImpressions,
        public int $productClicks,
        public int $recommendationImpressions,
        public int $recommendationClicks,
        public int $specialOfferImpressions,
        public int $specialOfferClicks,
    ) {
    }

    public function productCtr(): float
    {
        return $this->rate(
            $this->productClicks,
            $this->productImpressions
        );
    }

    public function recommendationCtr(): float
    {
        return $this->rate(
            $this->recommendationClicks,
            $this->recommendationImpressions
        );
    }

    public function specialOfferCtr(): float
    {
        return $this->rate(
            $this->specialOfferClicks,
            $this->specialOfferImpressions
        );
    }

    private function rate(
        int $clicks,
        int $impressions
    ): float {
        if ($impressions === 0) {
            return 0.0;
        }

        return round(
            ($clicks / $impressions) * 100,
            2
        );
    }
}
