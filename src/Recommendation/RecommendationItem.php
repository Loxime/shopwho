<?php

namespace App\Recommendation;

use App\Entity\Product;

final readonly class RecommendationItem
{
    public function __construct(
        public Product $product,
        public string $strategy,
    ) {
    }
}
