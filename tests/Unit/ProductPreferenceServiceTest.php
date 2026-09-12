<?php

namespace App\Tests\Unit;

use App\Entity\Product;
use App\Entity\User;
use App\ProductPreference\ProductPreferenceItem;
use App\Repository\ProductRepository;
use App\Repository\TrackingEventRepository;
use App\Service\ProductPreferenceService;
use PHPUnit\Framework\TestCase;

final class ProductPreferenceServiceTest extends TestCase
{
    public function testPreferencesUseWeightedBehavioralSignals(): void
    {
        $first =
            $this->product(101);

        $second =
            $this->product(102);

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
                'findProductInteractionCounts'
            )
            ->with(
                $user,
                array_keys(
                    ProductPreferenceService::
                        EVENT_WEIGHTS
                ),
                30
            )
            ->willReturn([
                [
                    'productId' => 101,
                    'eventType' => 'PRODUCT_VIEW',
                    'interactionCount' => 2,
                    'lastOccurredAt' =>
                        '2040-01-01T10:00:00+00:00',
                ],
                [
                    'productId' => 101,
                    'eventType' => 'ADD_TO_CART',
                    'interactionCount' => 1,
                    'lastOccurredAt' =>
                        '2040-01-01T10:05:00+00:00',
                ],
                [
                    'productId' => 102,
                    'eventType' => 'FAVORITE_ADDED',
                    'interactionCount' => 1,
                    'lastOccurredAt' =>
                        '2040-01-01T10:06:00+00:00',
                ],
                [
                    'productId' => 102,
                    'eventType' => 'REMOVE_FROM_CART',
                    'interactionCount' => 1,
                    'lastOccurredAt' =>
                        '2040-01-01T10:07:00+00:00',
                ],
                [
                    'productId' => 103,
                    'eventType' => 'FAVORITE_REMOVED',
                    'interactionCount' => 1,
                    'lastOccurredAt' =>
                        '2040-01-01T10:08:00+00:00',
                ],
            ]);

        $products
            ->expects(self::once())
            ->method(
                'findActiveByIds'
            )
            ->with([
                101,
                102,
            ])
            ->willReturn([
                $first,
                $second,
            ]);

        $service =
            new ProductPreferenceService(
                $products,
                $tracking
            );

        $preferences =
            $service->preferences(
                $user,
                8
            );

        self::assertCount(
            2,
            $preferences
        );

        self::assertContainsOnlyInstancesOf(
            ProductPreferenceItem::class,
            $preferences
        );

        self::assertSame(
            101,
            $preferences[0]
                ->product
                ->getId()
        );

        self::assertSame(
            7,
            $preferences[0]->score
        );

        self::assertSame(
            102,
            $preferences[1]
                ->product
                ->getId()
        );

        self::assertSame(
            3,
            $preferences[1]->score
        );
    }

    public function testPreferencesUseRecencyThenProductIdForTies(): void
    {
        $product202 =
            $this->product(202);

        $product203 =
            $this->product(203);

        $product201 =
            $this->product(201);

        $products = $this->createMock(
            ProductRepository::class
        );

        $tracking = $this->createMock(
            TrackingEventRepository::class
        );

        $user = new User();

        $tracking
            ->method(
                'findProductInteractionCounts'
            )
            ->willReturn([
                [
                    'productId' => 201,
                    'eventType' =>
                        'RECOMMENDATION_CLICK',
                    'interactionCount' => 1,
                    'lastOccurredAt' =>
                        '2040-01-01T09:00:00+00:00',
                ],
                [
                    'productId' => 203,
                    'eventType' =>
                        'RECOMMENDATION_CLICK',
                    'interactionCount' => 1,
                    'lastOccurredAt' =>
                        '2040-01-01T10:00:00+00:00',
                ],
                [
                    'productId' => 202,
                    'eventType' =>
                        'RECOMMENDATION_CLICK',
                    'interactionCount' => 1,
                    'lastOccurredAt' =>
                        '2040-01-01T10:00:00+00:00',
                ],
            ]);

        $products
            ->expects(self::once())
            ->method(
                'findActiveByIds'
            )
            ->with([
                202,
                203,
                201,
            ])
            ->willReturn([
                $product202,
                $product203,
                $product201,
            ]);

        $service =
            new ProductPreferenceService(
                $products,
                $tracking
            );

        $preferences =
            $service->preferences(
                $user,
                8
            );

        self::assertSame(
            [
                202,
                203,
                201,
            ],
            array_map(
                static fn (
                    ProductPreferenceItem $item
                ): ?int =>
                    $item->product->getId(),
                $preferences
            )
        );
    }

    public function testAnonymousUserHasNoAccountPreferences(): void
    {
        $products = $this->createMock(
            ProductRepository::class
        );

        $tracking = $this->createMock(
            TrackingEventRepository::class
        );

        $tracking
            ->expects(self::never())
            ->method(
                'findProductInteractionCounts'
            );

        $products
            ->expects(self::never())
            ->method(
                'findActiveByIds'
            );

        $service =
            new ProductPreferenceService(
                $products,
                $tracking
            );

        self::assertSame(
            [],
            $service->preferences(null)
        );
    }

    private function product(
        int $id
    ): Product {
        $product = new Product();

        $property =
            new \ReflectionProperty(
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
