<?php

namespace App\Controller;

use App\Entity\SpecialOffer;
use App\Repository\SpecialOfferRepository;
use App\Service\TrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SpecialOfferTrackingController extends AbstractController
{
    private const ALLOWED_EVENTS = [
        'SPECIAL_OFFER_IMPRESSION',
        'SPECIAL_OFFER_CLICK',
    ];

    private const ALLOWED_PLACEMENTS = [
        'header',
        'homepage',
    ];

    private const MAX_EVENTS = 25;

    #[Route(
        '/tracking/special-offers',
        name: 'app_tracking_special_offers',
        methods: ['POST']
    )]
    public function track(
        Request $request,
        SpecialOfferRepository $offers,
        TrackingService $tracking
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->json(
                ['error' => 'invalid_json'],
                400
            );
        }

        $token = $payload['_token'] ?? null;

        if (
            !is_string($token)
            || !$this->isCsrfTokenValid(
                'special_offer_tracking',
                $token
            )
        ) {
            return $this->json(
                ['error' => 'invalid_csrf'],
                403
            );
        }

        $events = $payload['events'] ?? null;

        if (
            !is_array($events)
            || $events === []
            || count($events) > self::MAX_EVENTS
        ) {
            return $this->json(
                ['error' => 'invalid_events'],
                422
            );
        }

        $sourcePath = $this->sourcePath(
            $payload['sourcePath'] ?? null
        );

        $offerIds = [];

        foreach ($events as $event) {
            if (
                is_array($event)
                && is_int($event['offerId'] ?? null)
                && $event['offerId'] > 0
            ) {
                $offerIds[] = $event['offerId'];
            }
        }

        $offerIds = array_values(
            array_unique($offerIds)
        );

        $offerMap = [];

        if ($offerIds !== []) {
            foreach (
                $offers->findBy([
                    'id' => $offerIds,
                ]) as $offer
            ) {
                $offerId = $offer->getId();

                if ($offerId !== null) {
                    $offerMap[$offerId] = $offer;
                }
            }
        }

        $normalizedEvents = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                return $this->json(
                    ['error' => 'invalid_event'],
                    422
                );
            }

            $eventType =
                $event['eventType'] ?? null;

            $offerId =
                $event['offerId'] ?? null;

            $placement =
                $event['placement'] ?? null;

            $position =
                $event['position'] ?? null;

            if (
                !is_string($eventType)
                || !in_array(
                    $eventType,
                    self::ALLOWED_EVENTS,
                    true
                )
            ) {
                return $this->json(
                    ['error' => 'invalid_event_type'],
                    422
                );
            }

            if (
                !is_int($offerId)
                || $offerId <= 0
            ) {
                return $this->json(
                    ['error' => 'invalid_offer_id'],
                    422
                );
            }

            if (
                !is_string($placement)
                || !in_array(
                    $placement,
                    self::ALLOWED_PLACEMENTS,
                    true
                )
            ) {
                return $this->json(
                    ['error' => 'invalid_placement'],
                    422
                );
            }

            if (
                !is_int($position)
                || $position < 1
                || $position > 8
            ) {
                return $this->json(
                    ['error' => 'invalid_position'],
                    422
                );
            }

            $offer = $offerMap[$offerId] ?? null;

            if (!$offer instanceof SpecialOffer) {
                return $this->json(
                    ['error' => 'unknown_offer'],
                    422
                );
            }

            if (
                $placement === 'header'
                && !$offer
                    ->getPlacement()
                    ->includesHeader()
            ) {
                return $this->json(
                    ['error' => 'invalid_offer_placement'],
                    422
                );
            }

            if (
                $placement === 'homepage'
                && !$offer
                    ->getPlacement()
                    ->includesHomepage()
            ) {
                return $this->json(
                    ['error' => 'invalid_offer_placement'],
                    422
                );
            }

            $metadata = [
                'offer_id' => $offerId,
                'placement' => $placement,
                'position' => $position,
                'configured_placement' =>
                    $offer->getPlacement()->value,
            ];

            $targetCategory =
                $offer->getTargetCategory();

            if ($targetCategory !== null) {
                $metadata['target_category'] =
                    $targetCategory->getSlug();
            }

            if (
                $offer->getExperimentKey()
                !== null
            ) {
                $metadata['experiment_key'] =
                    $offer->getExperimentKey();
            }

            if (
                $offer->getExperimentVariant()
                !== null
            ) {
                $metadata['experiment_variant'] =
                    $offer
                        ->getExperimentVariant();
            }

            if ($sourcePath !== null) {
                $metadata['source_path'] =
                    $sourcePath;
            }

            $normalizedEvents[] = [
                'eventType' => $eventType,
                'productId' => null,
                'metadata' => $metadata,
            ];
        }

        $tracking->trackBatch(
            $normalizedEvents
        );

        return $this->json(
            [
                'accepted' =>
                    count($normalizedEvents),
            ],
            202
        );
    }

    private function sourcePath(
        mixed $value
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (
            $value === ''
            || !str_starts_with(
                $value,
                '/'
            )
        ) {
            return null;
        }

        return mb_substr(
            $value,
            0,
            500
        );
    }
}
