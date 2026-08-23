<?php

namespace App\Tests;

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
}
