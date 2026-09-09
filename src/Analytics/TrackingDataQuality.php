<?php

namespace App\Analytics;

final readonly class TrackingDataQuality
{
    public function __construct(
        public int $totalEvents,
        public int $unknownEventTypes,
        public int $missingProductReferences,
        public int $missingRequiredProductIds,
        public int $emptyMetadataEvents,
        public int $mixedVisitorSessions,
    ) {
    }

    public function issueCount(): int
    {
        return
            $this->unknownEventTypes
            + $this->missingProductReferences
            + $this->missingRequiredProductIds
            + $this->mixedVisitorSessions;
    }

    public function isClean(): bool
    {
        return $this->issueCount() === 0;
    }
}
