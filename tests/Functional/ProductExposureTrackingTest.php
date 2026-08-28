<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\TrackingEvent;
use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\KernelInterface;

class ProductExposureTrackingTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
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
                    WHERE slug LIKE 'tracking-exposure-test-%'
                )
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM product
                WHERE slug LIKE 'tracking-exposure-test-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM category
                WHERE slug LIKE 'tracking-exposure-test-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM app_user
                WHERE email LIKE 'tracking-exposure-test-%@example.test'
            "
        );

        $this->em()->clear();

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testValidBatchStoresImpressionAndClick(): void
    {
        $client = static::createClient();

        [$product] = $this->fixture();

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $token = $this->trackingToken($client);

        $client->jsonRequest(
            'POST',
            '/tracking/catalog',
            [
                '_token' => $token,
                'query' => 'casque',
                'category' => 'audio',
                'sourcePath' => '/?q=casque&category=audio',
                'events' => [
                    [
                        'eventType' => 'PRODUCT_CARD_IMPRESSION',
                        'productId' => $product->getId(),
                        'position' => 3,
                    ],
                    [
                        'eventType' => 'PRODUCT_CARD_CLICK',
                        'productId' => $product->getId(),
                        'position' => 3,
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(202);

        $events = $this->eventsForProduct(
            $product
        );

        self::assertCount(2, $events);

        self::assertSame(
            'PRODUCT_CARD_IMPRESSION',
            $events[0]->getEventType()
        );

        self::assertSame(
            'PRODUCT_CARD_CLICK',
            $events[1]->getEventType()
        );

        self::assertSame(
            $events[0]->getVisitorId(),
            $events[1]->getVisitorId()
        );

        self::assertSame(
            $events[0]->getSessionId(),
            $events[1]->getSessionId()
        );
    }

    public function testTrackingStoresCatalogMetadata(): void
    {
        $client = static::createClient();

        [$product] = $this->fixture();

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $token = $this->trackingToken($client);

        $client->jsonRequest(
            'POST',
            '/tracking/catalog',
            [
                '_token' => $token,
                'query' => 'clavier',
                'category' => 'informatique',
                'sourcePath' => '/?q=clavier&category=informatique',
                'events' => [
                    [
                        'eventType' => 'PRODUCT_CARD_IMPRESSION',
                        'productId' => $product->getId(),
                        'position' => 7,
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(202);

        $event = $this->latestEventForProduct(
            $product
        );

        self::assertSame(
            [
                'path' => '/tracking/catalog',
                'method' => 'POST',
                'page' => 'catalog',
                'placement' => 'catalog',
                'position' => 7,
                'query' => 'clavier',
                'category' => 'informatique',
                'source_path' => '/?q=clavier&category=informatique',
            ],
            $event->getMetadata()
        );
    }

    public function testAuthenticatedTrackingStoresUser(): void
    {
        $client = static::createClient();

        [$product] = $this->fixture();
        $user = $this->createUser();

        $client->loginUser($user);

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $token = $this->trackingToken($client);

        $client->jsonRequest(
            'POST',
            '/tracking/catalog',
            [
                '_token' => $token,
                'events' => [
                    [
                        'eventType' => 'PRODUCT_CARD_CLICK',
                        'productId' => $product->getId(),
                        'position' => 1,
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(202);

        $event = $this->latestEventForProduct(
            $product
        );

        self::assertSame(
            $user->getId(),
            $event->getUser()?->getId()
        );
    }

    public function testRefusedConsentStoresNoExposureEvent(): void
    {
        $client = static::createClient();

        [$product] = $this->fixture();

        /*
         * On génère d'abord le token avec consentement,
         * puis on refuse avant l'appel de tracking.
         */
        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $token = $this->trackingToken($client);

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'no'
            )
        );

        $client->jsonRequest(
            'POST',
            '/tracking/catalog',
            [
                '_token' => $token,
                'events' => [
                    [
                        'eventType' => 'PRODUCT_CARD_IMPRESSION',
                        'productId' => $product->getId(),
                        'position' => 1,
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(202);

        self::assertCount(
            0,
            $this->eventsForProduct($product)
        );
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $client = static::createClient();

        [$product] = $this->fixture();

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $client->jsonRequest(
            'POST',
            '/tracking/catalog',
            [
                '_token' => 'invalid-token',
                'events' => [
                    [
                        'eventType' => 'PRODUCT_CARD_CLICK',
                        'productId' => $product->getId(),
                        'position' => 1,
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(403);

        self::assertCount(
            0,
            $this->eventsForProduct($product)
        );
    }

    public function testUnknownEventTypeIsRejected(): void
    {
        $client = static::createClient();

        [$product] = $this->fixture();

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $token = $this->trackingToken($client);

        $client->jsonRequest(
            'POST',
            '/tracking/catalog',
            [
                '_token' => $token,
                'events' => [
                    [
                        'eventType' => 'ARBITRARY_EVENT',
                        'productId' => $product->getId(),
                        'position' => 1,
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(422);

        self::assertCount(
            0,
            $this->eventsForProduct($product)
        );
    }

    public function testInvalidBatchSizeIsRejected(): void
    {
        $client = static::createClient();

        [$product] = $this->fixture();

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $token = $this->trackingToken($client);

        $events = [];

        for ($position = 1; $position <= 26; ++$position) {
            $events[] = [
                'eventType' => 'PRODUCT_CARD_IMPRESSION',
                'productId' => $product->getId(),
                'position' => $position,
            ];
        }

        $client->jsonRequest(
            'POST',
            '/tracking/catalog',
            [
                '_token' => $token,
                'events' => $events,
            ]
        );

        self::assertResponseStatusCodeSame(422);

        self::assertCount(
            0,
            $this->eventsForProduct($product)
        );
    }

    private function trackingToken(
        $client
    ): string {
        $crawler = $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        $catalogue = $crawler->filter(
            '[data-catalog-tracking]'
        );

        self::assertCount(
            1,
            $catalogue
        );

        $token = $catalogue->attr(
            'data-tracking-token'
        );

        self::assertNotNull($token);
        self::assertNotSame('', $token);

        return $token;
    }

    /**
     * @return array{Product}
     */
    private function fixture(): array
    {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $category = (new Category())
            ->setName(
                'Tracking exposure '.$suffix
            )
            ->setSlug(
                'tracking-exposure-test-category-'.$suffix
            );

        $product = (new Product())
            ->setName(
                'Tracking exposure '.$suffix
            )
            ->setSlug(
                'tracking-exposure-test-product-'.$suffix
            )
            ->setDescription(
                'Produit synthétique pour le test du tracking.'
            )
            ->setPriceCents(1999)
            ->setStock(10)
            ->setCategory($category);

        $this->em()->persist(
            $category
        );

        $this->em()->persist(
            $product
        );

        $this->em()->flush();

        return [$product];
    }

    private function createUser(): User
    {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $user = (new User())
            ->setEmail(
                'tracking-exposure-test-'
                .$suffix
                .'@example.test'
            )
            ->setFirstName('Tracking')
            ->setLastName('Exposure')
            ->setPassword('unused');

        $this->em()->persist(
            $user
        );

        $this->em()->flush();

        return $user;
    }

    /**
     * @return list<TrackingEvent>
     */
    private function eventsForProduct(
        Product $product
    ): array {
        $this->em()->clear();

        return $this->em()
            ->getRepository(
                TrackingEvent::class
            )
            ->findBy(
                [
                    'productId' =>
                        $product->getId(),
                ],
                [
                    'id' => 'ASC',
                ]
            );
    }

    private function latestEventForProduct(
        Product $product
    ): TrackingEvent {
        $events = $this->eventsForProduct(
            $product
        );

        self::assertNotEmpty(
            $events
        );

        $event = $events[
            array_key_last($events)
        ];

        self::assertInstanceOf(
            TrackingEvent::class,
            $event
        );

        return $event;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(
            EntityManagerInterface::class
        );
    }
}
