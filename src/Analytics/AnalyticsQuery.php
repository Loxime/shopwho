<?php

namespace App\Analytics;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

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
            (int) $row['favorite_adds'],
            (int) $row['recommendation_clicks'],
            (int) $row['special_offer_clicks'],
        );
    }
}
