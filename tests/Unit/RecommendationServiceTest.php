<?php

namespace App\Tests\Unit;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\TrackingEventRepository;
use App\Service\RecommendationService;
use PHPUnit\Framework\TestCase;

class RecommendationServiceTest extends TestCase
{
    public function testPersonalizedRecommendationsUseFallbacksInOrder(): void
    {
        $frequentlyViewed = $this->product(101);
        $popular = $this->product(102);
        $topRated = $this->product(103);

        $products = $this->createMock(
            ProductRepository::class
        );

        $tracking = $this->createMock(
            TrackingEventRepository::class
        );

        $user = new User();

        $tracking
            ->expects(self::once())
            ->method(
                'findFrequentlyViewedProductScores'
            )
            ->with(
                $user,
                30,
                9
            )
            ->willReturn([
                [
                    'productId' => 101,
                    'score' => 3,
                ],
            ]);

        $products
            ->expects(self::once())
            ->method('findActiveByIds')
            ->with([101])
            ->willReturn([
                $frequentlyViewed,
            ]);

        $products
            ->expects(self::once())
            ->method(
                'findPopularOrderedProducts'
            )
            ->with(
                30,
                6
            )
            ->willReturn([
                $popular,
            ]);

        $products
            ->expects(self::once())
            ->method(
                'findTopRatedProducts'
            )
            ->with(6)
            ->willReturn([
                $topRated,
            ]);

        $service = new RecommendationService(
            $products,
            $tracking
        );

        $recommendations = $service->recommend(
            $user,
            3
        );

        self::assertCount(
            3,
            $recommendations
        );

        self::assertSame(
            101,
            $recommendations[0]
                ->product
                ->getId()
        );

        self::assertSame(
            RecommendationService::STRATEGY_FREQUENTLY_VIEWED,
            $recommendations[0]->strategy
        );

        self::assertSame(
            102,
            $recommendations[1]
                ->product
                ->getId()
        );

        self::assertSame(
            RecommendationService::STRATEGY_POPULAR_30D,
            $recommendations[1]->strategy
        );

        self::assertSame(
            103,
            $recommendations[2]
                ->product
                ->getId()
        );

        self::assertSame(
            RecommendationService::STRATEGY_TOP_RATED,
            $recommendations[2]->strategy
        );
    }

    public function testAnonymousRecommendationsSkipPersonalization(): void
    {
        $popular = $this->product(201);
        $topRated = $this->product(202);

        $products = $this->createMock(
            ProductRepository::class
        );

        $tracking = $this->createMock(
            TrackingEventRepository::class
        );

        $tracking
            ->expects(self::never())
            ->method(
                'findFrequentlyViewedProductScores'
            );

        $products
            ->expects(self::never())
            ->method('findActiveByIds');

        $products
            ->expects(self::once())
            ->method(
                'findPopularOrderedProducts'
            )
            ->willReturn([
                $popular,
            ]);

        $products
            ->expects(self::once())
            ->method(
                'findTopRatedProducts'
            )
            ->willReturn([
                $topRated,
            ]);

        $service = new RecommendationService(
            $products,
            $tracking
        );

        $recommendations = $service->recommend(
            null,
            2
        );

        self::assertSame(
            [
                RecommendationService::STRATEGY_POPULAR_30D,
                RecommendationService::STRATEGY_TOP_RATED,
            ],
            array_map(
                static fn ($item) =>
                    $item->strategy,
                $recommendations
            )
        );
    }

    public function testInsufficientHistoryDoesNotEnablePersonalization(): void
    {
        $popular = $this->product(301);

        $products = $this->createMock(
            ProductRepository::class
        );

        $tracking = $this->createMock(
            TrackingEventRepository::class
        );

        $user = new User();

        $tracking
            ->method(
                'findFrequentlyViewedProductScores'
            )
            ->willReturn([
                [
                    'productId' => 999,
                    'score' => 2,
                ],
            ]);

        $products
            ->expects(self::never())
            ->method('findActiveByIds');

        $products
            ->method(
                'findPopularOrderedProducts'
            )
            ->willReturn([
                $popular,
            ]);

        $products
            ->method(
                'findTopRatedProducts'
            )
            ->willReturn([]);

        $service = new RecommendationService(
            $products,
            $tracking
        );

        $recommendations = $service->recommend(
            $user,
            1
        );

        self::assertCount(
            1,
            $recommendations
        );

        self::assertSame(
            RecommendationService::STRATEGY_POPULAR_30D,
            $recommendations[0]->strategy
        );
    }

    public function testFallbacksDoNotDuplicateProducts(): void
    {
        $shared = $this->product(401);
        $topRated = $this->product(402);

        $products = $this->createMock(
            ProductRepository::class
        );

        $tracking = $this->createMock(
            TrackingEventRepository::class
        );

        $products
            ->method(
                'findPopularOrderedProducts'
            )
            ->willReturn([
                $shared,
            ]);

        $products
            ->method(
                'findTopRatedProducts'
            )
            ->willReturn([
                $shared,
                $topRated,
            ]);

        $service = new RecommendationService(
            $products,
            $tracking
        );

        $recommendations = $service->recommend(
            null,
            2
        );

        self::assertCount(
            2,
            $recommendations
        );

        self::assertSame(
            [401, 402],
            array_map(
                static fn ($item) =>
                    $item->product->getId(),
                $recommendations
            )
        );

        self::assertSame(
            [
                RecommendationService::STRATEGY_POPULAR_30D,
                RecommendationService::STRATEGY_TOP_RATED,
            ],
            array_map(
                static fn ($item) =>
                    $item->strategy,
                $recommendations
            )
        );
    }

    private function product(
        int $id
    ): Product {
        $product = new Product();

        $property = new \ReflectionProperty(
            Product::class,
            'id'
        );

        $property->setValue(
            $product,
            $id
        );

        return $product;
    }
}
