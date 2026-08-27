<?php

namespace App\Analytics;

final readonly class ProductAnalytics
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $views,
        public int $cartAdds,
        public int $favoriteAdds,
        public int $recommendationClicks,
    ) {
    }

    public function cartRate(): float
    {
        if ($this->views === 0) {
            return 0.0;
        }

        return round(
            ($this->cartAdds / $this->views) * 100,
            2
        );
    }
}
