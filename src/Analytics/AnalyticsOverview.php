<?php

namespace App\Analytics;

final readonly class AnalyticsOverview
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public int $totalEvents,
        public int $uniqueVisitors,
        public int $uniqueSessions,
        public int $identifiedUsers,
        public int $productViews,
        public int $cartAdds,
        public int $checkoutStarts,
        public int $purchases,
        public int $favoriteAdds,
        public int $recommendationClicks,
        public int $specialOfferClicks,
    ) {
    }

    public function trackedSessionConversionRate(): float
    {
        if ($this->uniqueSessions === 0) {
            return 0.0;
        }

        return round(
            ($this->purchases / $this->uniqueSessions) * 100,
            2
        );
    }
}
