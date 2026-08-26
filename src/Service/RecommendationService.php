<?php

namespace App\Service;

use App\Entity\User;
use App\Recommendation\RecommendationItem;
use App\Repository\ProductRepository;
use App\Repository\TrackingEventRepository;

final class RecommendationService
{
    public const STRATEGY_FREQUENTLY_VIEWED =
        'frequently_viewed';

    public const STRATEGY_POPULAR_30D =
        'popular_30d';

    public const STRATEGY_TOP_RATED =
        'top_rated';

    private const PERSONALIZATION_MIN_VIEWS = 3;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly TrackingEventRepository $trackingEvents,
    ) {
    }

    /**
     * @return list<RecommendationItem>
     */
    public function recommend(
        ?User $user,
        int $limit = 8
    ): array {
        $limit = max(
            1,
            min($limit, 20)
        );

        $recommendations = [];
        $seenProductIds = [];

        if ($user !== null) {
            $scores =
                $this->trackingEvents
                    ->findFrequentlyViewedProductScores(
                        $user,
                        30,
                        $limit * 3
                    );

            $totalViews = array_sum(
                array_column(
                    $scores,
                    'score'
                )
            );

            if (
                $totalViews
                >= self::PERSONALIZATION_MIN_VIEWS
            ) {
                $ids = array_column(
                    $scores,
                    'productId'
                );

                foreach (
                    $this->products
                        ->findActiveByIds($ids)
                    as $product
                ) {
                    $this->append(
                        $recommendations,
                        $seenProductIds,
                        $product,
                        self::STRATEGY_FREQUENTLY_VIEWED,
                        $limit
                    );
                }
            }
        }

        if (
            count($recommendations)
            < $limit
        ) {
            foreach (
                $this->products
                    ->findPopularOrderedProducts(
                        30,
                        $limit * 2
                    )
                as $product
            ) {
                $this->append(
                    $recommendations,
                    $seenProductIds,
                    $product,
                    self::STRATEGY_POPULAR_30D,
                    $limit
                );
            }
        }

        if (
            count($recommendations)
            < $limit
        ) {
            foreach (
                $this->products
                    ->findTopRatedProducts(
                        $limit * 2
                    )
                as $product
            ) {
                $this->append(
                    $recommendations,
                    $seenProductIds,
                    $product,
                    self::STRATEGY_TOP_RATED,
                    $limit
                );
            }
        }

        return $recommendations;
    }

    /**
     * @param list<RecommendationItem> $recommendations
     * @param array<int, true> $seenProductIds
     */
    private function append(
        array &$recommendations,
        array &$seenProductIds,
        \App\Entity\Product $product,
        string $strategy,
        int $limit
    ): void {
        if (
            count($recommendations)
            >= $limit
        ) {
            return;
        }

        $productId = $product->getId();

        if (
            $productId === null
            || isset(
                $seenProductIds[$productId]
            )
        ) {
            return;
        }

        $seenProductIds[
            $productId
        ] = true;

        $recommendations[] =
            new RecommendationItem(
                $product,
                $strategy
            );
    }
}
