<?php

namespace App\Tests\Functional;

use App\Analytics\AnalyticsQuery;
use App\Entity\User;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class AdminAnalyticsAdvancedTest extends WebTestCase
{
    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true
        );
    }

    protected function tearDown(): void
    {
        if (!static::$booted) {
            static::bootKernel();
        }

        $connection = static::getContainer()
            ->get(Connection::class);

        $connection->executeStatement(
            <<<'SQL'
DELETE FROM tracking_event
WHERE visitor_id LIKE 'analytics-advanced-%'
SQL
        );

        $connection->executeStatement(
            <<<'SQL'
DELETE FROM app_user
WHERE email LIKE 'analytics-advanced-%@shopwho.test'
SQL
        );

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testInteractionAnalyticsUseImpressionsAndSessions(): void
    {
        static::createClient();

        $this->insertEvent(
            'visitor-1',
            'session-1',
            'PRODUCT_CARD_IMPRESSION'
        );

        $this->insertEvent(
            'visitor-1',
            'session-1',
            'PRODUCT_CARD_IMPRESSION'
        );

        $this->insertEvent(
            'visitor-1',
            'session-1',
            'PRODUCT_CARD_CLICK'
        );

        for ($index = 0; $index < 4; ++$index) {
            $this->insertEvent(
                'visitor-1',
                'session-1',
                'RECOMMENDATION_IMPRESSION'
            );
        }

        $this->insertEvent(
            'visitor-1',
            'session-1',
            'RECOMMENDATION_CLICK'
        );

        $this->insertEvent(
            'visitor-1',
            'session-1',
            'SPECIAL_OFFER_IMPRESSION'
        );

        $this->insertEvent(
            'visitor-1',
            'session-1',
            'SPECIAL_OFFER_IMPRESSION'
        );

        $this->insertEvent(
            'visitor-1',
            'session-1',
            'SPECIAL_OFFER_CLICK'
        );

        $this->insertEvent(
            'visitor-1',
            'session-1',
            'PURCHASE'
        );

        $this->insertEvent(
            'visitor-1',
            'session-1',
            'PURCHASE'
        );

        $this->insertEvent(
            'visitor-2',
            'session-2',
            'PAGE_VIEW'
        );

        $analytics = static::getContainer()
            ->get(AnalyticsQuery::class);

        $from = new \DateTimeImmutable(
            '2040-01-01T00:00:00+00:00'
        );

        $to = new \DateTimeImmutable(
            '2040-02-01T00:00:00+00:00'
        );

        $overview = $analytics->overview(
            $from,
            $to
        );

        $interactions = $analytics->interactions(
            $from,
            $to
        );

        self::assertSame(
            2,
            $overview->purchases
        );

        self::assertSame(
            1,
            $overview->purchaseSessions
        );

        self::assertSame(
            2,
            $overview->uniqueSessions
        );

        self::assertSame(
            50.0,
            $overview
                ->trackedSessionConversionRate()
        );

        self::assertSame(
            2,
            $interactions->productImpressions
        );

        self::assertSame(
            1,
            $interactions->productClicks
        );

        self::assertSame(
            50.0,
            $interactions->productCtr()
        );

        self::assertSame(
            4,
            $interactions
                ->recommendationImpressions
        );

        self::assertSame(
            1,
            $interactions
                ->recommendationClicks
        );

        self::assertSame(
            25.0,
            $interactions
                ->recommendationCtr()
        );

        self::assertSame(
            50.0,
            $interactions
                ->specialOfferCtr()
        );
    }

    public function testDataQualityDetectsContractProblems(): void
    {
        static::createClient();

        $this->insertEvent(
            'quality-a',
            'quality-a',
            'PAGE_VIEW',
            null,
            []
        );

        $this->insertEvent(
            'quality-b',
            'quality-b',
            'LEGACY_EVENT'
        );

        $this->insertEvent(
            'quality-c',
            'quality-c',
            'PRODUCT_VIEW'
        );

        $this->insertEvent(
            'quality-d',
            'quality-d',
            'PRODUCT_VIEW',
            987654321
        );

        $this->insertEvent(
            'mixed-a',
            'mixed-session',
            'PAGE_VIEW'
        );

        $this->insertEvent(
            'mixed-b',
            'mixed-session',
            'PAGE_VIEW'
        );

        $analytics = static::getContainer()
            ->get(AnalyticsQuery::class);

        $from = new \DateTimeImmutable(
            '2040-01-01T00:00:00+00:00'
        );

        $to = new \DateTimeImmutable(
            '2040-02-01T00:00:00+00:00'
        );

        $quality = $analytics->dataQuality(
            $from,
            $to
        );

        self::assertSame(
            6,
            $quality->totalEvents
        );

        self::assertSame(
            1,
            $quality->unknownEventTypes
        );

        self::assertSame(
            1,
            $quality->missingProductReferences
        );

        self::assertSame(
            1,
            $quality->missingRequiredProductIds
        );

        self::assertSame(
            1,
            $quality->emptyMetadataEvents
        );

        self::assertSame(
            1,
            $quality->mixedVisitorSessions
        );

        self::assertSame(
            4,
            $quality->issueCount()
        );

        self::assertFalse(
            $quality->isClean()
        );

        $coverage = $analytics->eventCoverage(
            $from,
            $to
        );

        $coverageByType = [];

        foreach ($coverage as $event) {
            $coverageByType[
                $event->eventType
            ] = $event;
        }

        self::assertSame(
            3,
            $coverageByType[
                'PAGE_VIEW'
            ]->count
        );

        self::assertSame(
            2,
            $coverageByType[
                'PRODUCT_VIEW'
            ]->count
        );

        self::assertSame(
            50.0,
            $coverageByType[
                'PAGE_VIEW'
            ]->shareOfEvents()
        );
    }

    public function testDataAnalystSeesAdvancedAnalytics(): void
    {
        $client = static::createClient();

        $user = (new User())
            ->setEmail(
                'analytics-advanced-'
                .bin2hex(
                    random_bytes(6)
                )
                .'@shopwho.test'
            )
            ->setFirstName(
                'Analytics'
            )
            ->setLastName(
                'Tester'
            )
            ->setPassword(
                'unused'
            )
            ->setRoles([
                'ROLE_DATA_ANALYST',
            ]);

        $em = static::getContainer()
            ->get(EntityManagerInterface::class);

        $em->persist(
            $user
        );

        $em->flush();

        $client->loginUser(
            $user
        );

        $client->request(
            'GET',
            '/admin/analytics?days=30'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.admin-interaction-analytics',
            'Taux de clic'
        );

        self::assertSelectorTextContains(
            '.admin-data-quality',
            'Qualité du dataset'
        );

        self::assertSelectorTextContains(
            'body',
            'Couverture du contrat d’événements'
        );
    }

    private function insertEvent(
        string $visitorSuffix,
        string $sessionSuffix,
        string $eventType,
        ?int $productId = null,
        array $metadata = [
            'source' => 'advanced-test',
        ]
    ): void {
        static::getContainer()
            ->get(Connection::class)
            ->executeStatement(
                <<<'SQL'
INSERT INTO tracking_event (
    visitor_id,
    session_id,
    event_type,
    product_id,
    user_id,
    metadata,
    occurred_at
)
VALUES (
    :visitor_id,
    :session_id,
    :event_type,
    :product_id,
    NULL,
    CAST(:metadata AS JSON),
    :occurred_at
)
SQL,
                [
                    'visitor_id' =>
                        'analytics-advanced-'
                        .$visitorSuffix,
                    'session_id' =>
                        'analytics-advanced-'
                        .$sessionSuffix,
                    'event_type' =>
                        $eventType,
                    'product_id' =>
                        $productId,
                    'metadata' =>
                        json_encode(
                            $metadata,
                            JSON_THROW_ON_ERROR
                        ),
                    'occurred_at' =>
                        '2040-01-15 12:00:00',
                ]
            );
    }
}
