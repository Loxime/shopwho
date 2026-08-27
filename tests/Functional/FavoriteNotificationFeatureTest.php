<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Favorite;
use App\Entity\Notification;
use App\Entity\Product;
use App\Entity\TrackingEvent;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Kernel;
use App\Repository\FavoriteRepository;
use App\Repository\NotificationRepository;
use App\Service\FavoriteProductNotificationService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\KernelInterface;

final class FavoriteNotificationFeatureTest extends WebTestCase
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

    public function testFavoriteAndNotificationPagesRequireAuthentication(): void
    {
        $this->client->request(
            'GET',
            '/profil/favoris'
        );

        self::assertResponseRedirects(
            '/connexion'
        );

        $this->client->request(
            'GET',
            '/profil/notifications'
        );

        self::assertResponseRedirects(
            '/connexion'
        );
    }

    public function testCustomerCanAddAndRemoveFavoriteFromProductPage(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();

        $this->client->loginUser($user);

        $crawler = $this->client->request(
            'GET',
            '/produit/'.$product->getSlug()
        );

        self::assertResponseIsSuccessful();

        $this->client->submit(
            $crawler
                ->selectButton(
                    'Ajouter aux favoris'
                )
                ->form()
        );

        self::assertResponseRedirects(
            '/produit/'.$product->getSlug()
        );

        self::assertSame(
            1,
            $this->favoriteRepository()->count([
                'user' => $user,
                'product' => $product,
            ])
        );

        $crawler =
            $this->client->followRedirect();

        $this->client->submit(
            $crawler
                ->selectButton(
                    'Retirer des favoris'
                )
                ->form()
        );

        self::assertResponseRedirects(
            '/produit/'.$product->getSlug()
        );

        self::assertSame(
            0,
            $this->favoriteRepository()->count([
                'user' => $user,
                'product' => $product,
            ])
        );
    }

    public function testFavoritesPageDisplaysFavoriteProduct(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();

        $this->createFavorite(
            $user,
            $product
        );

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/profil/favoris'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'h1',
            'Mes favoris'
        );

        self::assertSelectorTextContains(
            'body',
            $product->getName()
        );

        self::assertSelectorTextContains(
            '.favorite-card-actions .favorite-button',
            'Retirer'
        );
    }

    public function testFavoriteActionsAreTrackedWithConsent(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();

        $this->client
            ->getCookieJar()
            ->set(
                new Cookie(
                    'shopwho_tracking_consent',
                    'yes'
                )
            );

        $this->client->loginUser($user);

        $crawler = $this->client->request(
            'GET',
            '/produit/'.$product->getSlug()
        );

        $this->client->submit(
            $crawler
                ->selectButton(
                    'Ajouter aux favoris'
                )
                ->form()
        );

        $addedEvents = $this->em
            ->getRepository(
                TrackingEvent::class
            )
            ->findBy([
                'eventType' =>
                    'FAVORITE_ADDED',
                'productId' =>
                    $product->getId(),
                'user' =>
                    $user,
            ]);

        self::assertCount(
            1,
            $addedEvents
        );

        self::assertSame(
            'product',
            $addedEvents[0]
                ->getMetadata()['source']
                ?? null
        );

        $crawler =
            $this->client->followRedirect();

        $this->client->submit(
            $crawler
                ->selectButton(
                    'Retirer des favoris'
                )
                ->form()
        );

        $removedEvents = $this->em
            ->getRepository(
                TrackingEvent::class
            )
            ->findBy([
                'eventType' =>
                    'FAVORITE_REMOVED',
                'productId' =>
                    $product->getId(),
                'user' =>
                    $user,
            ]);

        self::assertCount(
            1,
            $removedEvents
        );
    }

    public function testNotificationCenterDisplaysUnreadNotificationAndBadge(): void
    {
        $user = $this->createUser();

        $this->createNotification(
            $user,
            'Notification de test',
            'Contenu de la notification.'
        );

        $this->client->loginUser($user);

        $this->client->request(
            'GET',
            '/profil/notifications'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'h1',
            'Mes notifications'
        );

        self::assertSelectorTextContains(
            'body',
            'Notification de test'
        );

        self::assertSelectorTextContains(
            '.notification-badge',
            '1'
        );
    }

    public function testOpeningNotificationMarksItAsRead(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();

        $notification =
            $this->createNotification(
                $user,
                'Produit favori',
                'Votre produit a changé.',
                '/produit/'
                    .$product->getSlug()
            );
            
        $notificationId = $notification->getId();

        self::assertNotNull($notificationId);
        
        $this->client->loginUser($user);

        $crawler = $this->client->request(
            'GET',
            '/profil/notifications'
        );

        $this->client->submit(
            $crawler
                ->selectButton('Voir')
                ->form()
        );

        self::assertResponseRedirects(
            '/produit/'.$product->getSlug()
        );

        $this->em->clear();

        $notification =
            $this->em
                ->getRepository(
                    Notification::class
                )
                ->find(
                    $notificationId
                );

        self::assertNotNull(
            $notification
        );

        self::assertTrue(
            $notification->isRead()
        );
        
        self::assertSame(
            0,
            $this
                ->notificationRepository()
                ->countUnreadForUser(
                    $user
                )
        );
    }

    public function testCustomerCannotOpenAnotherUsersNotification(): void
    {
        $owner = $this->createUser();
        $otherUser = $this->createUser();

        $notification =
            $this->createNotification(
                $owner,
                'Privée',
                'Notification privée.'
            );
            
        $notificationId =
            $notification->getId();

        self::assertNotNull(
            $notificationId
        );

        $this->client->loginUser(
            $otherUser
        );

        $this->client->request(
            'POST',
            '/profil/notifications/'
                .$notification->getId()
                .'/open',
            [
                '_token' => 'invalid',
            ]
        );

        self::assertResponseStatusCodeSame(
            404
        );
        
        $this->em->clear();

        $notification =
            $this->em
                ->getRepository(
                    Notification::class
                )
                ->find(
                    $notificationId
                );

        self::assertNotNull(
            $notification
        );

        self::assertFalse(
            $notification->isRead()
        );
    }

    public function testCustomerCanMarkAllNotificationsAsRead(): void
    {
        $user = $this->createUser();

        $this->createNotification(
            $user,
            'Notification 1',
            'Premier message.'
        );

        $this->createNotification(
            $user,
            'Notification 2',
            'Deuxième message.'
        );

        self::assertSame(
            2,
            $this
                ->notificationRepository()
                ->countUnreadForUser(
                    $user
                )
        );

        $this->client->loginUser($user);

        $crawler = $this->client->request(
            'GET',
            '/profil/notifications'
        );

        $this->client->submit(
            $crawler
                ->selectButton(
                    'Tout marquer comme lu'
                )
                ->form()
        );

        self::assertResponseRedirects(
            '/profil/notifications'
        );

        self::assertSame(
            0,
            $this
                ->notificationRepository()
                ->countUnreadForUser(
                    $user
                )
        );
    }

    public function testPriceDropCreatesFavoriteNotification(): void
    {
        $user = $this->createUser();

        $product = $this->createProduct(
            10000,
            10,
            true
        );

        $this->createFavorite(
            $user,
            $product
        );

        $product->setPriceCents(
            8000
        );

        $created =
            $this->notificationService()
                ->notifyForProductUpdate(
                    $product,
                    10000,
                    10,
                    true
                );

        $this->em->flush();

        self::assertSame(
            1,
            $created
        );

        $notifications =
            $this
                ->notificationRepository()
                ->findForUser(
                    $user
                );

        self::assertCount(
            1,
            $notifications
        );

        self::assertSame(
            NotificationType::FavoriteProduct,
            $notifications[0]->getType()
        );

        self::assertSame(
            'Baisse de prix sur un favori',
            $notifications[0]->getTitle()
        );

        self::assertStringContainsString(
            '100,00 €',
            $notifications[0]
                ->getMessage()
        );

        self::assertStringContainsString(
            '80,00 €',
            $notifications[0]
                ->getMessage()
        );

        self::assertSame(
            '/produit/'.$product->getSlug(),
            $notifications[0]
                ->getTargetUrl()
        );
    }

    public function testIrrelevantProductChangeDoesNotCreateNotification(): void
    {
        $user = $this->createUser();

        $product = $this->createProduct(
            10000,
            10,
            true
        );

        $this->createFavorite(
            $user,
            $product
        );

        $product->setDescription(
            'Nouvelle description'
        );

        $created =
            $this->notificationService()
                ->notifyForProductUpdate(
                    $product,
                    10000,
                    10,
                    true
                );

        $this->em->flush();

        self::assertSame(
            0,
            $created
        );

        self::assertSame(
            0,
            $this
                ->notificationRepository()
                ->count([
                    'user' => $user,
                ])
        );
    }

    public function testInactiveProductPriceDropDoesNotCreateNotification(): void
    {
        $user = $this->createUser();

        $product = $this->createProduct(
            10000,
            10,
            false
        );

        $this->createFavorite(
            $user,
            $product
        );

        $product->setPriceCents(
            8000
        );

        $created =
            $this->notificationService()
                ->notifyForProductUpdate(
                    $product,
                    10000,
                    10,
                    false
                );

        $this->em->flush();

        self::assertSame(
            0,
            $created
        );

        self::assertSame(
            0,
            $this
                ->notificationRepository()
                ->count([
                    'user' => $user,
                ])
        );
    }

    public function testPriceDropAndAvailabilityReturnCreateSingleCombinedNotification(): void
    {
        $user = $this->createUser();

        $product = $this->createProduct(
            10000,
            0,
            false
        );

        $this->createFavorite(
            $user,
            $product
        );

        $product
            ->setPriceCents(8000)
            ->setStock(5)
            ->setIsActive(true);

        $created =
            $this->notificationService()
                ->notifyForProductUpdate(
                    $product,
                    10000,
                    0,
                    false
                );

        $this->em->flush();

        self::assertSame(
            1,
            $created
        );

        $notifications =
            $this
                ->notificationRepository()
                ->findForUser(
                    $user
                );

        self::assertCount(
            1,
            $notifications
        );

        self::assertSame(
            'Bonne nouvelle pour un de vos favoris',
            $notifications[0]
                ->getTitle()
        );

        self::assertStringContainsString(
            '100,00 €',
            $notifications[0]
                ->getMessage()
        );

        self::assertStringContainsString(
            '80,00 €',
            $notifications[0]
                ->getMessage()
        );

        self::assertStringContainsString(
            'de nouveau disponible',
            $notifications[0]
                ->getMessage()
        );
    }

    private function createUser(): User
    {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $user = (new User())
            ->setEmail(
                'favorite-'.$suffix
                    .'@shopwho.local'
            )
            ->setFirstName('Client')
            ->setLastName('Test')
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
        int $priceCents = 10000,
        int $stock = 10,
        bool $active = true
    ): Product {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $category =
            (new Category())
                ->setName(
                    'Catégorie '.$suffix
                )
                ->setSlug(
                    'category-'.$suffix
                );

        $product =
            (new Product())
                ->setName(
                    'Produit '.$suffix
                )
                ->setSlug(
                    'product-'.$suffix
                )
                ->setDescription(
                    'Produit utilisé pour les tests.'
                )
                ->setPriceCents(
                    $priceCents
                )
                ->setStock(
                    $stock
                )
                ->setCategory(
                    $category
                )
                ->setIsActive(
                    $active
                );

        $this->em->persist(
            $category
        );

        $this->em->persist(
            $product
        );

        $this->em->flush();

        return $product;
    }

    private function createFavorite(
        User $user,
        Product $product
    ): Favorite {
        $favorite = new Favorite(
            $user,
            $product
        );

        $this->em->persist(
            $favorite
        );

        $this->em->flush();

        return $favorite;
    }

    private function createNotification(
        User $user,
        string $title,
        string $message,
        ?string $targetUrl = null
    ): Notification {
        $notification =
            new Notification(
                $user,
                NotificationType::System,
                $title,
                $message,
                $targetUrl
            );

        $this->em->persist(
            $notification
        );

        $this->em->flush();

        return $notification;
    }

    private function favoriteRepository(): FavoriteRepository
    {
        /** @var FavoriteRepository $repository */
        $repository =
            $this->em->getRepository(
                Favorite::class
            );

        return $repository;
    }

    private function notificationRepository(): NotificationRepository
    {
        /** @var NotificationRepository $repository */
        $repository =
            $this->em->getRepository(
                Notification::class
            );

        return $repository;
    }

    private function notificationService(): FavoriteProductNotificationService
    {
        /** @var FavoriteProductNotificationService $service */
        $service = static::getContainer()->get(
            FavoriteProductNotificationService::class
        );

        return $service;
    }
}
