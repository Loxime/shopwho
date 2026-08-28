<?php

namespace App\Tests\Functional;

use App\Analytics\AnalyticsQuery;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class AdminAnalyticsTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private Connection $connection;

    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $this->em = $em;
        $this->connection = $em->getConnection();

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (
            $this->connection->isTransactionActive()
        ) {
            $this->connection->rollBack();
        }

        $this->em->clear();

        parent::tearDown();
    }

    public function testAnalyticsQueryCalculatesOverviewFunnelAndTopProducts(): void
    {
        $user = $this->createUser(
            ['ROLE_USER']
        );

        $productA = $this->createProduct(
            'Produit Analytics A'
        );

        $productB = $this->createProduct(
            'Produit Analytics B'
        );

        $base = new \DateTimeImmutable(
            '2099-01-01 12:00:00'
        );

        // Session A : funnel complet.
        $this->insertEvent(
            'visitor-a',
            'session-a',
            'PRODUCT_VIEW',
            $base->modify('+1 minute'),
            $productA->getId(),
            $user->getId()
        );

        $this->insertEvent(
            'visitor-a',
            'session-a',
            'ADD_TO_CART',
            $base->modify('+2 minutes'),
            $productA->getId(),
            $user->getId()
        );

        $this->insertEvent(
            'visitor-a',
            'session-a',
            'CHECKOUT_STARTED',
            $base->modify('+3 minutes'),
            null,
            $user->getId()
        );

        $this->insertEvent(
            'visitor-a',
            'session-a',
            'PURCHASE',
            $base->modify('+4 minutes'),
            null,
            $user->getId()
        );

        $this->insertEvent(
            'visitor-a',
            'session-a',
            'FAVORITE_ADDED',
            $base->modify('+5 minutes'),
            $productA->getId(),
            $user->getId()
        );

        $this->insertEvent(
            'visitor-a',
            'session-a',
            'RECOMMENDATION_CLICK',
            $base->modify('+6 minutes'),
            $productA->getId(),
            $user->getId()
        );

        $this->insertEvent(
            'visitor-a',
            'session-a',
            'SPECIAL_OFFER_CLICK',
            $base->modify('+7 minutes'),
            null,
            $user->getId()
        );

        // Session B : vue puis panier.
        $this->insertEvent(
            'visitor-b',
            'session-b',
            'PRODUCT_VIEW',
            $base->modify('+10 minutes'),
            $productB->getId()
        );

        $this->insertEvent(
            'visitor-b',
            'session-b',
            'PRODUCT_VIEW',
            $base->modify('+11 minutes'),
            $productB->getId()
        );

        $this->insertEvent(
            'visitor-b',
            'session-b',
            'ADD_TO_CART',
            $base->modify('+12 minutes'),
            $productB->getId()
        );

        // Session C : panier avant la vue.
        // Elle ne doit donc pas franchir l'étape panier du funnel.
        $this->insertEvent(
            'visitor-c',
            'session-c',
            'ADD_TO_CART',
            $base->modify('+20 minutes'),
            $productA->getId()
        );

        $this->insertEvent(
            'visitor-c',
            'session-c',
            'PRODUCT_VIEW',
            $base->modify('+21 minutes'),
            $productA->getId()
        );

        // Hors période.
        $this->insertEvent(
            'visitor-old',
            'session-old',
            'PRODUCT_VIEW',
            $base->modify('-2 hours'),
            $productA->getId()
        );

        $query = $this->analyticsQuery();

        $from = $base;
        $to = $base->modify('+1 hour');

        $overview = $query->overview(
            $from,
            $to
        );

        self::assertSame(
            12,
            $overview->totalEvents
        );

        self::assertSame(
            3,
            $overview->uniqueVisitors
        );

        self::assertSame(
            3,
            $overview->uniqueSessions
        );

        self::assertSame(
            1,
            $overview->identifiedUsers
        );

        self::assertSame(
            4,
            $overview->productViews
        );

        self::assertSame(
            3,
            $overview->cartAdds
        );

        self::assertSame(
            1,
            $overview->checkoutStarts
        );

        self::assertSame(
            1,
            $overview->purchases
        );

        self::assertSame(
            1,
            $overview->favoriteAdds
        );

        self::assertSame(
            1,
            $overview->recommendationClicks
        );

        self::assertSame(
            1,
            $overview->specialOfferClicks
        );

        $funnel = $query->funnel(
            $from,
            $to
        );

        self::assertSame(
            3,
            $funnel->productViewSessions
        );

        self::assertSame(
            2,
            $funnel->cartSessions
        );

        self::assertSame(
            1,
            $funnel->checkoutSessions
        );

        self::assertSame(
            1,
            $funnel->purchaseSessions
        );

        self::assertSame(
            66.67,
            $funnel->viewToCartRate()
        );

        self::assertSame(
            33.33,
            $funnel->viewToPurchaseRate()
        );

        $products = $query->topProducts(
            $from,
            $to,
            10
        );

        self::assertCount(
            2,
            $products
        );

        self::assertSame(
            $productA->getId(),
            $products[0]->id
        );

        self::assertSame(
            2,
            $products[0]->views
        );

        self::assertSame(
            2,
            $products[0]->cartAdds
        );

        self::assertSame(
            1,
            $products[0]->favoriteAdds
        );

        self::assertSame(
            1,
            $products[0]->recommendationClicks
        );

        self::assertSame(
            100.0,
            $products[0]->cartRate()
        );

        self::assertSame(
            $productB->getId(),
            $products[1]->id
        );

        self::assertSame(
            2,
            $products[1]->views
        );

        self::assertSame(
            1,
            $products[1]->cartAdds
        );

        self::assertSame(
            50.0,
            $products[1]->cartRate()
        );
    }

    public function testAnonymousUserCannotAccessAnalytics(): void
    {
        $this->client->request(
            'GET',
            '/admin/analytics'
        );

        self::assertResponseRedirects(
            '/connexion'
        );
    }

    public function testAdminCanDisplayAnalyticsDashboard(): void
    {
        $admin = $this->createUser(
            ['ROLE_ADMIN']
        );

        $this->client->loginUser(
            $admin
        );

        $this->client->request(
            'GET',
            '/admin/analytics?days=7'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'h1',
            'Analytics'
        );

        self::assertSelectorTextContains(
            'body',
            'Funnel comportemental'
        );

        self::assertSelectorTextContains(
            'body',
            'Produits les plus engagés'
        );

        self::assertSelectorExists(
            '#analytics-period option[value="7"][selected]'
        );
    }

    public function testInvalidPeriodFallsBackToThirtyDays(): void
    {
        $admin = $this->createUser(
            ['ROLE_ADMIN']
        );

        $this->client->loginUser(
            $admin
        );

        $this->client->request(
            'GET',
            '/admin/analytics?days=999'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '#analytics-period option[value="30"][selected]'
        );
    }

    private function analyticsQuery(): AnalyticsQuery
    {
        /** @var AnalyticsQuery $query */
        $query = static::getContainer()->get(
            AnalyticsQuery::class
        );

        return $query;
    }

    private function createUser(
        array $roles
    ): User {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $user = (new User())
            ->setEmail(
                'analytics-'.$suffix
                    .'@shopwho.local'
            )
            ->setRoles($roles)
            ->setPassword(
                'test-password-hash'
            );

        $this->em->persist(
            $user
        );

        $this->em->flush();

        return $user;
    }

    private function createProduct(
        string $name
    ): Product {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $category = (new Category())
            ->setName(
                'Analytics '.$suffix
            )
            ->setSlug(
                'analytics-'.$suffix
            );

        $product = (new Product())
            ->setName($name)
            ->setSlug(
                'analytics-product-'.$suffix
            )
            ->setDescription(
                'Produit destiné aux tests analytics.'
            )
            ->setPriceCents(1000)
            ->setStock(10)
            ->setCategory($category)
            ->setIsActive(true);

        $this->em->persist(
            $category
        );

        $this->em->persist(
            $product
        );

        $this->em->flush();

        return $product;
    }

    private function insertEvent(
        string $visitorId,
        string $sessionId,
        string $eventType,
        \DateTimeImmutable $occurredAt,
        ?int $productId = null,
        ?int $userId = null
    ): void {
        $this->connection->executeStatement(
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
    :user_id,
    :metadata,
    :occurred_at
)
SQL,
            [
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'event_type' => $eventType,
                'product_id' => $productId,
                'user_id' => $userId,
                'metadata' => '{}',
                'occurred_at' => $occurredAt,
            ],
            [
                'occurred_at' =>
                    Types::DATETIME_IMMUTABLE,
            ]
        );
    }
}
