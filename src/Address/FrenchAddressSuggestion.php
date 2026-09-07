<?php

namespace App\Address;

final readonly class FrenchAddressSuggestion
{
    public function __construct(
        public string $label,
        public string $name,
        public string $postalCode,
        public string $city,
        public string $cityCode,
        public string $type,
        public float $score,
    ) {
    }
}
