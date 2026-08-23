<?php

namespace App\Tests;

use App\Entity\Category;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function testPriceConversionKeepsCentsConsistent(): void
    {
        $product = (new Product())->setPrice(19.99);

        self::assertSame(1999, $product->getPriceCents());
        self::assertSame(19.99, $product->getPrice());
    }

    public function testProductCanBeDisabled(): void
    {
        $product = (new Product())->setIsActive(false);

        self::assertFalse($product->isActive());
    }

    public function testProductUsesNormalizedCategory(): void
    {
        $category = (new Category())->setName('High-tech')->setSlug('high-tech');
        $product = (new Product())->setCategory($category);

        self::assertSame($category, $product->getCategory());
        self::assertSame('High-tech', (string) $product->getCategory());
    }
}
