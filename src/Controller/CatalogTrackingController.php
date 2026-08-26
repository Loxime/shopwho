<?php

namespace App\Controller;

use App\Service\TrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class CatalogTrackingController extends AbstractController
{
    private const ALLOWED_EVENTS = [
        'PRODUCT_CARD_IMPRESSION',
        'PRODUCT_CARD_CLICK',
    ];

    private const MAX_EVENTS = 25;

    #[Route(
        '/tracking/catalog',
        name: 'app_tracking_catalog',
        methods: ['POST'],
    )]
    public function track(
        Request $request,
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
                'catalog_tracking',
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

        $query = $this->optionalString(
            $payload['query'] ?? null,
            200
        );

        $category = $this->optionalString(
            $payload['category'] ?? null,
            120
        );

        $sourcePath = $this->sourcePath(
            $payload['sourcePath'] ?? null
        );

        $normalizedEvents = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                return $this->json(
                    ['error' => 'invalid_event'],
                    422
                );
            }

            $eventType = $event['eventType'] ?? null;
            $productId = $event['productId'] ?? null;
            $position = $event['position'] ?? null;

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
                !is_int($productId)
                || $productId <= 0
            ) {
                return $this->json(
                    ['error' => 'invalid_product_id'],
                    422
                );
            }

            if (
                !is_int($position)
                || $position < 1
                || $position > 500
            ) {
                return $this->json(
                    ['error' => 'invalid_position'],
                    422
                );
            }

            $metadata = [
                'page' => 'catalog',
                'placement' => 'catalog',
                'position' => $position,
            ];

            if ($query !== null) {
                $metadata['query'] = $query;
            }

            if ($category !== null) {
                $metadata['category'] = $category;
            }

            if ($sourcePath !== null) {
                $metadata['source_path'] = $sourcePath;
            }

            $normalizedEvents[] = [
                'eventType' => $eventType,
                'productId' => $productId,
                'metadata' => $metadata,
            ];
        }

        $tracking->trackBatch($normalizedEvents);

        return $this->json(
            [
                'accepted' => count(
                    $normalizedEvents
                ),
            ],
            202
        );
    }

    private function optionalString(
        mixed $value,
        int $maxLength
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr(
            $value,
            0,
            $maxLength
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
            || !str_starts_with($value, '/')
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
