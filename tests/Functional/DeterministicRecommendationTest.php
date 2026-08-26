<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\TrackingEvent;
use App\Entity\User;
use App\Kernel;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\KernelInterface;

class DeterministicRecommendationTest extends WebTestCase
{
    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
        );
    }

    protected function tearDown(): void
    {
        if (!static::$booted) {
            static::bootKernel();
        }

        $connection = $this->em()->getConnection();

        $connection->executeStatement(
            "
                DELETE FROM tracking_event
                WHERE product_id IN (
                    SELECT id
                    FROM product
                    WHERE slug LIKE 'rec-test-%'
                )
                OR user_id IN (
                    SELECT id
                    FROM app_user
                    WHERE email LIKE 'rec-test-%@example.test'
                )
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM product_review
                WHERE product_id IN (
                    SELECT id
                    FROM product
                    WHERE slug LIKE 'rec-test-%'
                )
                OR user_id IN (
                    SELECT id
                    FROM app_user
                    WHERE email LIKE 'rec-test-%@example.test'
                )
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM customer_order
                WHERE user_id IN (
                    SELECT id
                    FROM app_user
                    WHERE email LIKE 'rec-test-%@example.test'
                )
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM product
                WHERE slug LIKE 'rec-test-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM category
                WHERE slug LIKE 'rec-test-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM app_user
                WHERE email LIKE 'rec-test-%@example.test'
            "
        );

        $this->em()->clear();

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testAuthenticatedUserGetsFrequentlyViewedFirst(): void
    {
        static::bootKernel();

        $user = $this->createUser();
        $viewed = $this->createProduct();

        $this->createViews(
            $user,
            $viewed,
            3
        );

        $recommendations = $this
            ->recommendationService()
            ->recommend(
                $user,
                8
            );

        self::assertNotEmpty(
            $recommendations
        );

        self::assertSame(
            $viewed->getId(),
            $recommendations[0]
                ->product
                ->getId()
        );

        self::assertSame(
            RecommendationService::STRATEGY_FREQUENTLY_VIEWED,
            $recommendations[0]->strategy
        );
    }

    public function testAnonymousRecommendationsStartWithPopularProducts(): void
    {
        static::bootKernel();

        $popular = $this->createProduct();

        /*
         * Quantité volontairement très élevée pour rendre
         * cette fixture dominante même si la base de test
         * contient déjà d'autres commandes.
         */
        $this->createOrder(
            $popular,
            100000
        );

        $recommendations = $this
            ->recommendationService()
            ->recommend(
                null,
                8
            );

        self::assertNotEmpty(
            $recommendations
        );

        self::assertSame(
            $popular->getId(),
            $recommendations[0]
                ->product
                ->getId()
        );

        self::assertSame(
            RecommendationService::STRATEGY_POPULAR_30D,
            $recommendations[0]->strategy
        );
    }

    public function testRecommendationListContainsNoDuplicates(): void
    {
        static::bootKernel();

        $product = $this->createProduct();

        /*
         * Le même produit est éligible via deux stratégies.
         */
        $this->createOrder(
            $product,
            100000
        );

        $this->createReview(
            $product,
            5
        );

        $recommendations = $this
            ->recommendationService()
            ->recommend(
                null,
                8
            );

        $ids = array_map(
            static fn ($item) =>
                $item->product->getId(),
            $recommendations
        );

        self::assertNotEmpty(
            $ids
        );

        self::assertSame(
            count($ids),
            count(
                array_unique($ids)
            )
        );

        self::assertSame(
            1,
            count(
                array_filter(
                    $ids,
                    static fn ($id) =>
                        $id === $product->getId()
                )
            )
        );
    }

    public function testInactiveFrequentlyViewedProductIsNeverRecommended(): void
    {
        static::bootKernel();

        $user = $this->createUser();

        $inactive = $this->createProduct(
            false
        );

        $this->createViews(
            $user,
            $inactive,
            10
        );

        $recommendations = $this
            ->recommendationService()
            ->recommend(
                $user,
                20
            );

        $ids = array_map(
            static fn ($item) =>
                $item->product->getId(),
            $recommendations
        );

        self::assertNotContains(
            $inactive->getId(),
            $ids
        );
    }

    public function testRecommendationTrackingStoresMetadataAndUser(): void
    {
        $client = static::createClient();

        $user = $this->createUser();
        $product = $this->createProduct();

        $this->createReview(
            $product,
            5
        );

        $client->loginUser($user);

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $token = $this->trackingToken(
            $client
        );

        $client->jsonRequest(
            'POST',
            '/tracking/recommendations',
            [
                '_token' => $token,
                'sourcePath' => '/?source=test',
                'events' => [
                    [
                        'eventType' =>
                            'RECOMMENDATION_IMPRESSION',
                        'productId' =>
                            $product->getId(),
                        'position' => 1,
                        'strategy' =>
                            'top_rated',
                    ],
                    [
                        'eventType' =>
                            'RECOMMENDATION_CLICK',
                        'productId' =>
                            $product->getId(),
                        'position' => 1,
                        'strategy' =>
                            'top_rated',
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(
            202
        );

        $events = $this
            ->recommendationEvents(
                $product
            );

        self::assertCount(
            2,
            $events
        );

        self::assertSame(
            'RECOMMENDATION_IMPRESSION',
            $events[0]->getEventType()
        );

        self::assertSame(
            'RECOMMENDATION_CLICK',
            $events[1]->getEventType()
        );

        self::assertSame(
            $user->getId(),
            $events[0]->getUser()?->getId()
        );

        self::assertSame(
            $events[0]->getVisitorId(),
            $events[1]->getVisitorId()
        );

        self::assertSame(
            $events[0]->getSessionId(),
            $events[1]->getSessionId()
        );

        self::assertSame(
            'homepage_recommendations',
            $events[0]
                ->getMetadata()['placement']
        );

        self::assertSame(
            'top_rated',
            $events[0]
                ->getMetadata()['strategy']
        );

        self::assertSame(
            1,
            $events[0]
                ->getMetadata()['position']
        );

        self::assertSame(
            '/?source=test',
            $events[0]
                ->getMetadata()['source_path']
        );
    }

    public function testInvalidRecommendationStrategyIsRejected(): void
    {
        $client = static::createClient();

        $product = $this->createProduct();

        $this->createReview(
            $product,
            5
        );

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $token = $this->trackingToken(
            $client
        );

        $client->jsonRequest(
            'POST',
            '/tracking/recommendations',
            [
                '_token' => $token,
                'events' => [
                    [
                        'eventType' =>
                            'RECOMMENDATION_CLICK',
                        'productId' =>
                            $product->getId(),
                        'position' => 1,
                        'strategy' =>
                            'made_up_strategy',
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(
            422
        );

        self::assertCount(
            0,
            $this->recommendationEvents(
                $product
            )
        );
    }

    public function testRefusedConsentCreatesNoRecommendationEvent(): void
    {
        $client = static::createClient();

        $product = $this->createProduct();

        $this->createReview(
            $product,
            5
        );

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $token = $this->trackingToken(
            $client
        );

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'no'
            )
        );

        $client->jsonRequest(
            'POST',
            '/tracking/recommendations',
            [
                '_token' => $token,
                'events' => [
                    [
                        'eventType' =>
                            'RECOMMENDATION_IMPRESSION',
                        'productId' =>
                            $product->getId(),
                        'position' => 1,
                        'strategy' =>
                            'top_rated',
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(
            202
        );

        self::assertCount(
            0,
            $this->recommendationEvents(
                $product
            )
        );
    }

    private function createProduct(
        bool $active = true
    ): Product {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $category = (new Category())
            ->setName(
                'Recommendation '.$suffix
            )
            ->setSlug(
                'rec-test-category-'.$suffix
            );

        $product = (new Product())
            ->setName(
                'Recommendation '.$suffix
            )
            ->setSlug(
                'rec-test-product-'.$suffix
            )
            ->setDescription(
                'Produit de test pour les recommandations.'
            )
            ->setPriceCents(2500)
            ->setStock(20)
            ->setIsActive($active)
            ->setCategory($category);

        $this->em()->persist(
            $category
        );

        $this->em()->persist(
            $product
        );

        $this->em()->flush();

        return $product;
    }

    private function createUser(): User
    {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $user = (new User())
            ->setEmail(
                'rec-test-'
                .$suffix
                .'@example.test'
            )
            ->setFirstName('Recommendation')
            ->setLastName('Test')
            ->setPassword('unused');

        $this->em()->persist(
            $user
        );

        $this->em()->flush();

        return $user;
    }

    private function createViews(
        User $user,
        Product $product,
        int $count
    ): void {
        for (
            $index = 0;
            $index < $count;
            ++$index
        ) {
            $event = new TrackingEvent(
                'rec-test-visitor',
                'rec-test-session',
                'PRODUCT_VIEW',
                $product->getId(),
                [
                    'page' => 'product',
                ]
            );

            $event->setUser(
                $user
            );

            $this->em()->persist(
                $event
            );
        }

        $this->em()->flush();
    }

    private function createOrder(
        Product $product,
        int $quantity
    ): void {
        $user = $this->createUser();

        $suffix = strtoupper(
            bin2hex(
                random_bytes(5)
            )
        );

        $order = new Order(
            $user,
            'REC-'.$suffix,
            $product->getPriceCents()
                * $quantity,
            new \DateTimeImmutable('-1 day')
        );

        OrderItem::fromProduct(
            $order,
            $product,
            $quantity
        );

        $this->em()->persist(
            $order
        );

        $this->em()->flush();
    }

    private function createReview(
        Product $product,
        int $rating
    ): void {
        $user = $this->createUser();

        $review = new Review(
            $user,
            $product,
            $rating
        );

        $this->em()->persist(
            $review
        );

        $this->em()->flush();
    }

    private function trackingToken(
        $client
    ): string {
        $crawler = $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        $section = $crawler->filter(
            '[data-recommendation-tracking]'
        );

        self::assertCount(
            1,
            $section
        );

        $token = $section->attr(
            'data-tracking-token'
        );

        self::assertNotNull(
            $token
        );

        self::assertNotSame(
            '',
            $token
        );

        return $token;
    }

    /**
     * @return list<TrackingEvent>
     */
    private function recommendationEvents(
        Product $product
    ): array {
        $this->em()->clear();

        return $this->em()
            ->getRepository(
                TrackingEvent::class
            )
            ->createQueryBuilder('event')
            ->andWhere(
                'event.productId = :productId'
            )
            ->andWhere(
                'event.eventType IN (:types)'
            )
            ->setParameter(
                'productId',
                $product->getId()
            )
            ->setParameter(
                'types',
                [
                    'RECOMMENDATION_IMPRESSION',
                    'RECOMMENDATION_CLICK',
                ]
            )
            ->orderBy(
                'event.id',
                'ASC'
            )
            ->getQuery()
            ->getResult();
    }

    private function recommendationService():
        RecommendationService
    {
        return static::getContainer()->get(
            RecommendationService::class
        );
    }

    private function em():
        EntityManagerInterface
    {
        return static::getContainer()->get(
            EntityManagerInterface::class
        );
    }
}
