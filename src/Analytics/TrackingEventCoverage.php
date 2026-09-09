<?php

namespace App\Analytics;

final readonly class TrackingEventCoverage
{
    public function __construct(
        public string $eventType,
        public int $count,
        public int $totalEvents,
    ) {
    }

    public function shareOfEvents(): float
    {
        if ($this->totalEvents === 0) {
            return 0.0;
        }

        return round(
            ($this->count / $this->totalEvents) * 100,
            2
        );
    }
}
