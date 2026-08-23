<?php

namespace App\Tests;

use App\Entity\Category;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    public function testCategoryExposesNavigationConfiguration(): void
    {
        $category = (new Category())
            ->setName('High-tech')
            ->setSlug('high-tech')
            ->setIcon('fa-solid fa-laptop')
            ->setIsFeatured(true)
            ->setShowInNavigation(false)
            ->setNavigationPosition(20);

        self::assertSame('High-tech', $category->getName());
        self::assertSame('high-tech', $category->getSlug());
        self::assertSame('fa-solid fa-laptop', $category->getIcon());
        self::assertTrue($category->isFeatured());
        self::assertFalse($category->isShowInNavigation());
        self::assertSame(20, $category->getNavigationPosition());
    }

    public function testNewCategoryStartsWithoutProducts(): void
    {
        $category = new Category();

        self::assertFalse($category->hasProducts());
        self::assertCount(0, $category->getProducts());
    }
}
