<?php

namespace App\ProductPreference;

use App\Entity\Product;

final readonly class ProductPreferenceItem
{
    public function __construct(
        public Product $product,
        public int $score,
    ) {
    }
}
