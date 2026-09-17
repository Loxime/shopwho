<?php

namespace App\Service;

use App\Entity\ExperimentVariant;
use App\Entity\User;
use App\Recommendation\RecommendationItem;
use App\Repository\ExperimentRepository;

final class RecommendationExperimentService
{
    public const EXPERIMENT_KEY =
        'homepage-recommendation-fallback';

    public const VARIANT_CONTROL =
        'control';

    public const VARIANT_CANDIDATE =
        'candidate';

    private const CANDIDATE_STRATEGY_ORDER = [
        RecommendationService::STRATEGY_FREQUENTLY_VIEWED,
        RecommendationService::STRATEGY_TOP_RATED,
        RecommendationService::STRATEGY_POPULAR_30D,
    ];

    public function __construct(
        private readonly ExperimentRepository $experiments,
        private readonly ExperimentAssignmentService $assignments,
        private readonly RecommendationService $recommendations,
    ) {
    }

    /**
     * @return list<RecommendationItem>
     */
    public function recommend(
        ?User $user,
        int $limit = 8
    ): array {
        $variant =
            $this->assignedVariant();

        return $this->recommendations->recommend(
            $user,
            $limit,
            $this->strategyOrder(
                $variant
            )
        );
    }

    /**
     * @return array{
     *     experiment_key: string,
     *     experiment_variant: string
     * }|array{}
     */
    public function trackingMetadata(): array
    {
        $variant =
            $this->assignedVariant();

        if ($variant === null) {
            return [];
        }

        $experiment =
            $variant->getExperiment();

        if (
            $experiment === null
            || $experiment->getKey()
                !== self::EXPERIMENT_KEY
        ) {
            return [];
        }

        return [
            'experiment_key' =>
                $experiment->getKey(),
            'experiment_variant' =>
                $variant->getKey(),
        ];
    }

    private function assignedVariant():
        ?ExperimentVariant
    {
        $experiment =
            $this->experiments->findByKey(
                self::EXPERIMENT_KEY
            );

        if ($experiment === null) {
            return null;
        }

        $variant =
            $this->assignments->assign(
                $experiment
            );

        if ($variant === null) {
            return null;
        }

        if (
            !in_array(
                $variant->getKey(),
                [
                    self::VARIANT_CONTROL,
                    self::VARIANT_CANDIDATE,
                ],
                true
            )
        ) {
            return null;
        }

        return $variant;
    }

    /**
     * @return list<string>
     */
    private function strategyOrder(
        ?ExperimentVariant $variant
    ): array {
        if (
            $variant?->getKey()
            === self::VARIANT_CANDIDATE
        ) {
            return self::CANDIDATE_STRATEGY_ORDER;
        }

        return RecommendationService::
            DEFAULT_STRATEGY_ORDER;
    }
}
