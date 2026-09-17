<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\TrackingEventType;
use App\ProductPreference\ProductPreferenceItem;
use App\Repository\ProductRepository;
use App\Repository\TrackingEventRepository;

final class ProductPreferenceService
{
    public const EVENT_WEIGHTS = [
        TrackingEventType::ProductView->value => 1,
        TrackingEventType::ProductCardClick->value => 2,
        TrackingEventType::RecommendationClick->value => 3,
        TrackingEventType::AddToCart->value => 5,
        TrackingEventType::FavoriteAdded->value => 6,
        TrackingEventType::RemoveFromCart->value => -3,
        TrackingEventType::FavoriteRemoved->value => -6,
    ];

    private const DEFAULT_DAYS = 30;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly TrackingEventRepository $trackingEvents,
    ) {
    }

    /**
     * @return list<ProductPreferenceItem>
     */
    public function preferences(
        ?User $user,
        int $limit = 8
    ): array {
        if ($user === null) {
            return [];
        }

        $limit = max(
            1,
            min(
                $limit,
                20
            )
        );

        $rows = $this->trackingEvents
            ->findProductInteractionCounts(
                $user,
                array_keys(
                    self::EVENT_WEIGHTS
                ),
                self::DEFAULT_DAYS
            );

        /**
         * @var array<int, array{
         *     productId: int,
         *     score: int,
         *     lastOccurredAt: int
         * }> $scores
         */
        $scores = [];

        foreach ($rows as $row) {
            $eventType =
                $row['eventType'];

            $weight =
                self::EVENT_WEIGHTS[
                    $eventType
                ] ?? null;

            if ($weight === null) {
                continue;
            }

            $productId =
                $row['productId'];

            $occurredAt =
                new \DateTimeImmutable(
                    $row['lastOccurredAt']
                );

            if (
                !isset(
                    $scores[$productId]
                )
            ) {
                $scores[$productId] = [
                    'productId' =>
                        $productId,
                    'score' =>
                        0,
                    'lastOccurredAt' =>
                        $occurredAt
                            ->getTimestamp(),
                ];
            }

            $scores[$productId]['score'] +=
                $weight
                * $row['interactionCount'];

            $scores[
                $productId
            ][
                'lastOccurredAt'
            ] = max(
                $scores[
                    $productId
                ][
                    'lastOccurredAt'
                ],
                $occurredAt
                    ->getTimestamp()
            );
        }

        $scores = array_filter(
            $scores,
            static fn (
                array $score
            ): bool =>
                $score['score'] > 0
        );

        uasort(
            $scores,
            static function (
                array $left,
                array $right
            ): int {
                $byScore =
                    $right['score']
                    <=>
                    $left['score'];

                if ($byScore !== 0) {
                    return $byScore;
                }

                $byRecency =
                    $right[
                        'lastOccurredAt'
                    ]
                    <=>
                    $left[
                        'lastOccurredAt'
                    ];

                if ($byRecency !== 0) {
                    return $byRecency;
                }

                return
                    $left['productId']
                    <=>
                    $right['productId'];
            }
        );

        $orderedProductIds =
            array_column(
                $scores,
                'productId'
            );

        $scoreByProductId = [];

        foreach ($scores as $score) {
            $scoreByProductId[
                $score['productId']
            ] = $score['score'];
        }

        $preferences = [];

        foreach (
            $this->products
                ->findActiveByIds(
                    $orderedProductIds
                )
            as $product
        ) {
            $productId =
                $product->getId();

            if (
                $productId === null
                || !isset(
                    $scoreByProductId[
                        $productId
                    ]
                )
            ) {
                continue;
            }

            $preferences[] =
                new ProductPreferenceItem(
                    $product,
                    $scoreByProductId[
                        $productId
                    ]
                );

            if (
                count($preferences)
                >= $limit
            ) {
                break;
            }
        }

        return $preferences;
    }
}
