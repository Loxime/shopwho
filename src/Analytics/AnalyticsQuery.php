<?php

namespace App\Analytics;

use App\Enum\TrackingEventType;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\DBAL\ParameterType;

final readonly class AnalyticsQuery
{
    public function __construct(
        private Connection $connection
    ) {
    }

    public function overview(
        \DateTimeImmutable $from,
        ?\DateTimeImmutable $to = null
    ): AnalyticsOverview {
        $to ??= new \DateTimeImmutable();

        $row = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT
    COUNT(*) AS total_events,
    COUNT(DISTINCT visitor_id) AS unique_visitors,
    COUNT(DISTINCT session_id) AS unique_sessions,
    COUNT(DISTINCT user_id)
        FILTER (WHERE user_id IS NOT NULL)
        AS identified_users,
    COUNT(*)
        FILTER (WHERE event_type = 'PRODUCT_VIEW')
        AS product_views,
    COUNT(*)
        FILTER (WHERE event_type = 'ADD_TO_CART')
        AS cart_adds,
    COUNT(*)
        FILTER (WHERE event_type = 'CHECKOUT_STARTED')
        AS checkout_starts,
    COUNT(*)
        FILTER (WHERE event_type = 'PURCHASE')
        AS purchases,
    COUNT(DISTINCT session_id)
        FILTER (WHERE event_type = 'PURCHASE')
        AS purchase_sessions,
    COUNT(*)
        FILTER (WHERE event_type = 'FAVORITE_ADDED')
        AS favorite_adds,
    COUNT(*)
        FILTER (WHERE event_type = 'RECOMMENDATION_CLICK')
        AS recommendation_clicks,
    COUNT(*)
        FILTER (WHERE event_type = 'SPECIAL_OFFER_CLICK')
        AS special_offer_clicks
FROM tracking_event
WHERE occurred_at >= :from
  AND occurred_at <= :to
SQL,
            [
                'from' => $from,
                'to' => $to,
            ],
            [
                'from' => Types::DATETIME_IMMUTABLE,
                'to' => Types::DATETIME_IMMUTABLE,
            ]
        );

        if ($row === false) {
            throw new \RuntimeException(
                'Impossible de calculer les statistiques de tracking.'
            );
        }

        return new AnalyticsOverview(
            $from,
            $to,
            (int) $row['total_events'],
            (int) $row['unique_visitors'],
            (int) $row['unique_sessions'],
            (int) $row['identified_users'],
            (int) $row['product_views'],
            (int) $row['cart_adds'],
            (int) $row['checkout_starts'],
            (int) $row['purchases'],
            (int) $row['purchase_sessions'],
            (int) $row['favorite_adds'],
            (int) $row['recommendation_clicks'],
            (int) $row['special_offer_clicks'],
        );
    }

    public function funnel(
    \DateTimeImmutable $from,
    ?\DateTimeImmutable $to = null
): AnalyticsFunnel {
    $to ??= new \DateTimeImmutable();

    $row = $this->connection->fetchAssociative(
        <<<'SQL'
WITH session_steps AS (
    SELECT
        session_id,
        MIN(occurred_at)
            FILTER (
                WHERE event_type = 'PRODUCT_VIEW'
            ) AS product_view_at,
        MIN(occurred_at)
            FILTER (
                WHERE event_type = 'ADD_TO_CART'
            ) AS cart_at,
        MIN(occurred_at)
            FILTER (
                WHERE event_type = 'CHECKOUT_STARTED'
            ) AS checkout_at,
        MIN(occurred_at)
            FILTER (
                WHERE event_type = 'PURCHASE'
            ) AS purchase_at
    FROM tracking_event
    WHERE occurred_at >= :from
      AND occurred_at <= :to
    GROUP BY session_id
)
SELECT
    COUNT(*)
        FILTER (
            WHERE product_view_at IS NOT NULL
        ) AS product_view_sessions,

    COUNT(*)
        FILTER (
            WHERE product_view_at IS NOT NULL
              AND cart_at >= product_view_at
        ) AS cart_sessions,

    COUNT(*)
        FILTER (
            WHERE product_view_at IS NOT NULL
              AND cart_at >= product_view_at
              AND checkout_at >= cart_at
        ) AS checkout_sessions,

    COUNT(*)
        FILTER (
            WHERE product_view_at IS NOT NULL
              AND cart_at >= product_view_at
              AND checkout_at >= cart_at
              AND purchase_at >= checkout_at
        ) AS purchase_sessions
FROM session_steps
SQL,
        [
            'from' => $from,
            'to' => $to,
        ],
        [
            'from' =>
                Types::DATETIME_IMMUTABLE,
            'to' =>
                Types::DATETIME_IMMUTABLE,
        ]
    );

    if ($row === false) {
        throw new \RuntimeException(
            'Impossible de calculer le funnel.'
        );
    }

    return new AnalyticsFunnel(
        (int) $row['product_view_sessions'],
        (int) $row['cart_sessions'],
        (int) $row['checkout_sessions'],
        (int) $row['purchase_sessions'],
    );
}

/**
 * @return list<ProductAnalytics>
 */
public function topProducts(
    \DateTimeImmutable $from,
    ?\DateTimeImmutable $to = null,
    int $limit = 10
): array {
    $to ??= new \DateTimeImmutable();

    $rows = $this->connection->fetchAllAssociative(
        <<<'SQL'
SELECT
    product.id,
    product.name,
    product.slug,

    COUNT(*)
        FILTER (
            WHERE event.event_type = 'PRODUCT_VIEW'
        ) AS views,

    COUNT(*)
        FILTER (
            WHERE event.event_type = 'ADD_TO_CART'
        ) AS cart_adds,

    COUNT(*)
        FILTER (
            WHERE event.event_type = 'FAVORITE_ADDED'
        ) AS favorite_adds,

    COUNT(*)
        FILTER (
            WHERE event.event_type = 'RECOMMENDATION_CLICK'
        ) AS recommendation_clicks

FROM tracking_event event

INNER JOIN product
    ON product.id = event.product_id

WHERE event.occurred_at >= :from
  AND event.occurred_at <= :to
  AND event.product_id IS NOT NULL

GROUP BY
    product.id,
    product.name,
    product.slug

ORDER BY
    views DESC,
    cart_adds DESC,
    favorite_adds DESC,
    product.id ASC

LIMIT :limit
SQL,
        [
            'from' => $from,
            'to' => $to,
            'limit' => max(
                1,
                min($limit, 50)
            ),
        ],
        [
            'from' =>
                Types::DATETIME_IMMUTABLE,
            'to' =>
                Types::DATETIME_IMMUTABLE,
            'limit' =>
                ParameterType::INTEGER,
        ]
    );

    return array_map(
        static fn (array $row):
            ProductAnalytics =>
                new ProductAnalytics(
                    (int) $row['id'],
                    (string) $row['name'],
                    (string) $row['slug'],
                    (int) $row['views'],
                    (int) $row['cart_adds'],
                    (int) $row['favorite_adds'],
                    (int) $row[
                        'recommendation_clicks'
                    ],
                ),
        $rows
    );
}

    public function interactions(
        \DateTimeImmutable $from,
        ?\DateTimeImmutable $to = null
    ): InteractionAnalytics {
        $to ??= new \DateTimeImmutable();

        $row = $this->connection
            ->fetchAssociative(
                <<<'SQL'
SELECT
    COUNT(*)
        FILTER (
            WHERE event_type =
                'PRODUCT_CARD_IMPRESSION'
        ) AS product_impressions,

    COUNT(*)
        FILTER (
            WHERE event_type =
                'PRODUCT_CARD_CLICK'
        ) AS product_clicks,

    COUNT(*)
        FILTER (
            WHERE event_type =
                'RECOMMENDATION_IMPRESSION'
        ) AS recommendation_impressions,

    COUNT(*)
        FILTER (
            WHERE event_type =
                'RECOMMENDATION_CLICK'
        ) AS recommendation_clicks,

    COUNT(*)
        FILTER (
            WHERE event_type =
                'SPECIAL_OFFER_IMPRESSION'
        ) AS special_offer_impressions,

    COUNT(*)
        FILTER (
            WHERE event_type =
                'SPECIAL_OFFER_CLICK'
        ) AS special_offer_clicks

FROM tracking_event

WHERE occurred_at >= :from
  AND occurred_at <= :to
SQL,
                [
                    'from' => $from,
                    'to' => $to,
                ],
                [
                    'from' =>
                        Types::DATETIME_IMMUTABLE,
                    'to' =>
                        Types::DATETIME_IMMUTABLE,
                ]
            );

        if ($row === false) {
            throw new \RuntimeException(
                'Impossible de calculer '
                .'les analytics d’interaction.'
            );
        }

        return new InteractionAnalytics(
            (int) $row['product_impressions'],
            (int) $row['product_clicks'],
            (int) $row[
                'recommendation_impressions'
            ],
            (int) $row[
                'recommendation_clicks'
            ],
            (int) $row[
                'special_offer_impressions'
            ],
            (int) $row[
                'special_offer_clicks'
            ],
        );
    }


    public function dataQuality(
        \DateTimeImmutable $from,
        ?\DateTimeImmutable $to = null
    ): TrackingDataQuality {
        $to ??= new \DateTimeImmutable();

        $knownEventTypes =
            TrackingEventType::values();

        $productRequiredEvents = [
            TrackingEventType::ProductView->value,
            TrackingEventType::ProductCardImpression->value,
            TrackingEventType::ProductCardClick->value,
            TrackingEventType::AddToCart->value,
            TrackingEventType::CartQuantityChanged->value,
            TrackingEventType::RemoveFromCart->value,
            TrackingEventType::FavoriteAdded->value,
            TrackingEventType::FavoriteRemoved->value,
            TrackingEventType::RecommendationImpression->value,
            TrackingEventType::RecommendationClick->value,
        ];

        $row = $this->connection
            ->fetchAssociative(
                <<<'SQL'
WITH filtered AS (
    SELECT
        event_type,
        product_id,
        metadata,
        session_id,
        visitor_id
    FROM tracking_event
    WHERE occurred_at >= :from
      AND occurred_at <= :to
),
event_quality AS (
    SELECT
        COUNT(*) AS total_events,

        COUNT(*)
            FILTER (
                WHERE filtered.event_type
                    NOT IN (:known_event_types)
            ) AS unknown_event_types,

        COUNT(*)
            FILTER (
                WHERE filtered.product_id IS NOT NULL
                  AND product.id IS NULL
            ) AS missing_product_references,

        COUNT(*)
            FILTER (
                WHERE filtered.event_type
                    IN (:product_required_events)
                  AND filtered.product_id IS NULL
            ) AS missing_required_product_ids,

        COUNT(*)
            FILTER (
                WHERE filtered.metadata::jsonb =
                    '{}'::jsonb
            ) AS empty_metadata_events

    FROM filtered

    LEFT JOIN product
        ON product.id = filtered.product_id
),
session_quality AS (
    SELECT
        COUNT(*) AS mixed_visitor_sessions
    FROM (
        SELECT
            session_id
        FROM filtered
        GROUP BY session_id
        HAVING COUNT(
            DISTINCT visitor_id
        ) > 1
    ) mixed_sessions
)

SELECT
    event_quality.*,
    session_quality.mixed_visitor_sessions

FROM event_quality
CROSS JOIN session_quality
SQL,
                [
                    'from' => $from,
                    'to' => $to,
                    'known_event_types' =>
                        $knownEventTypes,
                    'product_required_events' =>
                        $productRequiredEvents,
                ],
                [
                    'from' =>
                        Types::DATETIME_IMMUTABLE,
                    'to' =>
                        Types::DATETIME_IMMUTABLE,
                    'known_event_types' =>
                        ArrayParameterType::STRING,
                    'product_required_events' =>
                        ArrayParameterType::STRING,
                ]
            );

        if ($row === false) {
            throw new \RuntimeException(
                'Impossible de calculer '
                .'la qualité du tracking.'
            );
        }

        return new TrackingDataQuality(
            (int) $row['total_events'],
            (int) $row['unknown_event_types'],
            (int) $row[
                'missing_product_references'
            ],
            (int) $row[
                'missing_required_product_ids'
            ],
            (int) $row[
                'empty_metadata_events'
            ],
            (int) $row[
                'mixed_visitor_sessions'
            ],
        );
    }

    /**
     * @return list<TrackingEventCoverage>
     */
    public function eventCoverage(
        \DateTimeImmutable $from,
        ?\DateTimeImmutable $to = null
    ): array {
        $to ??= new \DateTimeImmutable();

        $rows = $this->connection
            ->fetchAllAssociative(
                <<<'SQL'
SELECT
    event_type,
    COUNT(*) AS event_count

FROM tracking_event

WHERE occurred_at >= :from
  AND occurred_at <= :to

GROUP BY event_type

ORDER BY event_type ASC
SQL,
                [
                    'from' => $from,
                    'to' => $to,
                ],
                [
                    'from' =>
                        Types::DATETIME_IMMUTABLE,
                    'to' =>
                        Types::DATETIME_IMMUTABLE,
                ]
            );

        $counts = [];
        $totalEvents = 0;

        foreach ($rows as $row) {
            $count =
                (int) $row['event_count'];

            $counts[
                (string) $row['event_type']
            ] = $count;

            $totalEvents += $count;
        }

        return array_map(
            static fn (
                TrackingEventType $event
            ): TrackingEventCoverage =>
                new TrackingEventCoverage(
                    $event->value,
                    $counts[
                        $event->value
                    ] ?? 0,
                    $totalEvents
                ),
            TrackingEventType::cases()
        );
    }

}
