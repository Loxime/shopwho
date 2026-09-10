<?php

namespace App\Service;

use App\Entity\Experiment;
use App\Entity\ExperimentVariant;
use Symfony\Component\HttpFoundation\RequestStack;

final class ExperimentAssignmentService
{
    private const TRAFFIC_BUCKETS = 10000;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TrackingIdentityService $trackingIdentity,
    ) {
    }

    public function assign(
        Experiment $experiment,
        ?\DateTimeImmutable $now = null
    ): ?ExperimentVariant {
        $request =
            $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        $identity =
            $this->trackingIdentity->resolve(
                $request
            );

        if ($identity === null) {
            return null;
        }

        return $this->assignForVisitor(
            $experiment,
            $identity->getVisitorId(),
            $now
        );
    }

    public function assignForVisitor(
        Experiment $experiment,
        string $visitorId,
        ?\DateTimeImmutable $now = null
    ): ?ExperimentVariant {
        $visitorId = trim($visitorId);

        if (
            $visitorId === ''
            || !$experiment->isAssignable($now)
        ) {
            return null;
        }

        $variants = array_values(
            array_filter(
                $experiment
                    ->getVariants()
                    ->toArray(),
                static fn (
                    ExperimentVariant $variant
                ): bool => $variant->getWeight() > 0
            )
        );

        if (count($variants) < 2) {
            return null;
        }

        usort(
            $variants,
            static fn (
                ExperimentVariant $left,
                ExperimentVariant $right
            ): int => strcmp(
                $left->getKey(),
                $right->getKey()
            )
        );

        $trafficLimit =
            $experiment->getTrafficPercentage()
            * 100;

        $trafficBucket = $this->bucket(
            'traffic',
            $experiment->getKey(),
            $visitorId,
            self::TRAFFIC_BUCKETS
        );

        if ($trafficBucket >= $trafficLimit) {
            return null;
        }

        $totalWeight = array_sum(
            array_map(
                static fn (
                    ExperimentVariant $variant
                ): int => $variant->getWeight(),
                $variants
            )
        );

        if ($totalWeight <= 0) {
            return null;
        }

        $variantBucket = $this->bucket(
            'variant',
            $experiment->getKey(),
            $visitorId,
            $totalWeight
        );

        $cursor = 0;

        foreach ($variants as $variant) {
            $cursor += $variant->getWeight();

            if ($variantBucket < $cursor) {
                return $variant;
            }
        }

        return null;
    }

    private function bucket(
        string $purpose,
        string $experimentKey,
        string $visitorId,
        int $bucketCount
    ): int {
        if ($bucketCount <= 0) {
            throw new \InvalidArgumentException(
                'Le nombre de buckets doit être positif.'
            );
        }

        $hash = hash(
            'sha256',
            implode(
                "\0",
                [
                    'shopwho-ab-v1',
                    $purpose,
                    $experimentKey,
                    $visitorId,
                ]
            )
        );

        $value = hexdec(
            substr($hash, 0, 8)
        );

        return (int) (
            $value % $bucketCount
        );
    }
}
