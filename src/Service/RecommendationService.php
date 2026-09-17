<?php

namespace App\Service;

use App\Entity\Product;
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

    public const DEFAULT_STRATEGY_ORDER = [
        self::STRATEGY_FREQUENTLY_VIEWED,
        self::STRATEGY_POPULAR_30D,
        self::STRATEGY_TOP_RATED,
    ];

    private const PERSONALIZATION_MIN_VIEWS = 3;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly TrackingEventRepository $trackingEvents,
    ) {
    }

    /**
     * @param list<string>|null $strategyOrder
     *
     * @return list<RecommendationItem>
     */
    public function recommend(
        ?User $user,
        int $limit = 8,
        ?array $strategyOrder = null
    ): array {
        $limit = max(
            1,
            min($limit, 20)
        );

        $strategyOrder ??=
            self::DEFAULT_STRATEGY_ORDER;

        $this->assertStrategyOrder(
            $strategyOrder
        );

        $recommendations = [];
        $seenProductIds = [];

        foreach ($strategyOrder as $strategy) {
            if (
                count($recommendations)
                >= $limit
            ) {
                break;
            }

            switch ($strategy) {
                case self::STRATEGY_FREQUENTLY_VIEWED:
                    $this->appendFrequentlyViewed(
                        $recommendations,
                        $seenProductIds,
                        $user,
                        $limit
                    );
                    break;

                case self::STRATEGY_POPULAR_30D:
                    $this->appendPopular(
                        $recommendations,
                        $seenProductIds,
                        $limit
                    );
                    break;

                case self::STRATEGY_TOP_RATED:
                    $this->appendTopRated(
                        $recommendations,
                        $seenProductIds,
                        $limit
                    );
                    break;
            }
        }

        return $recommendations;
    }

    /**
     * @param list<RecommendationItem> $recommendations
     * @param array<int, true> $seenProductIds
     */
    private function appendFrequentlyViewed(
        array &$recommendations,
        array &$seenProductIds,
        ?User $user,
        int $limit
    ): void {
        if ($user === null) {
            return;
        }

        $scores = $this->trackingEvents
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
            < self::PERSONALIZATION_MIN_VIEWS
        ) {
            return;
        }

        $ids = array_column(
            $scores,
            'productId'
        );

        foreach (
            $this->products->findActiveByIds(
                $ids
            )
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

    /**
     * @param list<RecommendationItem> $recommendations
     * @param array<int, true> $seenProductIds
     */
    private function appendPopular(
        array &$recommendations,
        array &$seenProductIds,
        int $limit
    ): void {
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

    /**
     * @param list<RecommendationItem> $recommendations
     * @param array<int, true> $seenProductIds
     */
    private function appendTopRated(
        array &$recommendations,
        array &$seenProductIds,
        int $limit
    ): void {
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

    /**
     * @param list<RecommendationItem> $recommendations
     * @param array<int, true> $seenProductIds
     */
    private function append(
        array &$recommendations,
        array &$seenProductIds,
        Product $product,
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

        $seenProductIds[$productId] = true;

        $recommendations[] =
            new RecommendationItem(
                $product,
                $strategy
            );
    }

    /**
     * @param list<string> $strategyOrder
     */
    private function assertStrategyOrder(
        array $strategyOrder
    ): void {
        foreach ($strategyOrder as $strategy) {
            if (!is_string($strategy)) {
                throw new \InvalidArgumentException(
                    'L’ordre des stratégies de recommandation est invalide.'
                );
            }
        }

        $expected =
            self::DEFAULT_STRATEGY_ORDER;

        $actual = $strategyOrder;

        sort($expected);
        sort($actual);

        if ($actual !== $expected) {
            throw new \InvalidArgumentException(
                'L’ordre doit contenir exactement chaque stratégie de recommandation une fois.'
            );
        }
    }
}
