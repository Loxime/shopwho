<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\TrackingEvent;
use App\Entity\User;
use App\Kernel;
use App\Service\TrackingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\KernelInterface;

class CustomerOrderTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testAnonymousCheckoutStillWorksWithoutCreatingOrder(): void
    {
        $client = static::createClient();
        [$product] = $this->createCatalogFixture();
        $before = $this->em()->getRepository(Order::class)->count([]);
        $client->request('POST', '/panier/ajouter/'.$product->getId());
        $client->request('GET', '/panier');
        $client->submitForm('Simuler la commande');

        self::assertResponseRedirects('/panier');
        self::assertSame($before, $this->em()->getRepository(Order::class)->count([]));
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Commande simulée avec succès');
        self::assertSelectorTextContains('body', 'Votre panier est vide');
    }

    public function testAuthenticatedCheckoutCreatesOneOrderTracksPurchaseAndEmptiesCart(): void
    {
        $client = static::createClient();
        [$product, $user] = $this->createCatalogFixture(true);
        $client->loginUser($user);
        $client->getCookieJar()->set(new Cookie('shopwho_tracking_consent', 'yes'));
        $before = $this->em()->getRepository(Order::class)->count([]);
        $client->request('POST', '/panier/ajouter/'.$product->getId());
        $client->request('GET', '/panier');
        $client->submitForm('Simuler la commande');

        self::assertResponseRedirects('/panier');
        $em = $this->em();
        $em->clear();
        self::assertSame($before + 1, $em->getRepository(Order::class)->count([]));
        $order = $em->getRepository(Order::class)->findOneBy(['user' => $user], ['id' => 'DESC']);
        self::assertInstanceOf(Order::class, $order);
        self::assertCount(1, $order->getItems());
        $purchase = $em->getRepository(TrackingEvent::class)->findOneBy(['eventType' => 'PURCHASE'], ['id' => 'DESC']);
        self::assertInstanceOf(TrackingEvent::class, $purchase);
        self::assertSame($order->getId(), $purchase->getMetadata()['order_id']);
        self::assertSame($order->getReference(), $purchase->getMetadata()['order_reference']);
        self::assertSame($order->getTotalCents(), $purchase->getMetadata()['total_cents']);
        self::assertSame(1, $purchase->getMetadata()['line_count']);
        $client->request('GET', '/panier');
        self::assertSelectorTextContains('body', 'Votre panier est vide');
    }

    public function testEmptyCartDoesNotCreateOrder(): void
    {
        $client = static::createClient();
        [$product, $user] = $this->createCatalogFixture(true);
        $client->loginUser($user);
        $before = $this->em()->getRepository(Order::class)->count([]);
        $client->request('POST', '/panier/ajouter/'.$product->getId());
        $crawler = $client->request('GET', '/panier');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/panier/retirer/'.$product->getId());
        $client->request('POST', '/panier/commander', ['_token' => $token]);

        self::assertResponseRedirects('/panier');
        self::assertSame($before, $this->em()->getRepository(Order::class)->count([]));
    }

    public function testCheckoutIgnoresProductWhoseStockReachedZero(): void
    {
        $client = static::createClient();
        [$product, $user] = $this->createCatalogFixture(true);
        $client->loginUser($user);
        $before = $this->em()->getRepository(Order::class)->count([]);
        $client->request('POST', '/panier/ajouter/'.$product->getId());
        $crawler = $client->request('GET', '/panier');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $managedProduct = $this->em()->find(Product::class, $product->getId());
        self::assertInstanceOf(Product::class, $managedProduct);
        $managedProduct->setStock(0);
        $this->em()->flush();
        $client->request('POST', '/panier/commander', ['_token' => $token]);

        self::assertResponseRedirects('/panier');
        self::assertSame($before, $this->em()->getRepository(Order::class)->count([]));
    }

    public function testInvalidCheckoutCsrfDoesNotCreateOrderTrackPurchaseOrEmptyCart(): void
    {
        $client = static::createClient();
        [$product, $user] = $this->createCatalogFixture(true);
        $client->loginUser($user);
        $client->getCookieJar()->set(new Cookie('shopwho_tracking_consent', 'yes'));
        $em = $this->em();
        $orderCount = $em->getRepository(Order::class)->count([]);
        $purchaseCount = $em->getRepository(TrackingEvent::class)->count(['eventType' => 'PURCHASE']);
        $client->request('POST', '/panier/ajouter/'.$product->getId());
        $client->request('POST', '/panier/commander', ['_token' => 'invalid']);

        self::assertResponseRedirects('/panier');
        self::assertSame($orderCount, $em->getRepository(Order::class)->count([]));
        self::assertSame($purchaseCount, $em->getRepository(TrackingEvent::class)->count(['eventType' => 'PURCHASE']));
        $client->followRedirect();
        self::assertSelectorTextContains('body', $product->getName());
    }

    public function testPurchaseFailureRollsBackOrderAndKeepsCart(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        [$product, $user] = $this->createCatalogFixture(true);
        $tracking = $this->createMock(TrackingService::class);
        $tracking->method('track')->willReturnCallback(static function (string $eventType): void {
            if ('PURCHASE' === $eventType) {
                throw new \RuntimeException('Simulated PURCHASE failure');
            }
        });
        static::getContainer()->set(TrackingService::class, $tracking);
        $client->loginUser($user);
        $client->getCookieJar()->set(new Cookie('shopwho_tracking_consent', 'yes'));
        $client->request('POST', '/panier/ajouter/'.$product->getId());
        $client->request('GET', '/panier');
        $before = (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM customer_order');
        $client->catchExceptions(false);

        try {
            $client->submitForm('Simuler la commande');
            self::fail('The simulated PURCHASE failure should have been thrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Simulated PURCHASE failure', $exception->getMessage());
        }

        self::assertSame($before, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM customer_order'));
        self::assertTrue($client->getRequest()->getSession()->has('cart'));
    }

    public function testOrderRoutesRequireAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profil/commandes');

        self::assertResponseRedirects('/connexion');
    }

    public function testProfileShowsAtMostFiveMostRecentOrders(): void
    {
        $client = static::createClient();
        [$product, $user] = $this->createCatalogFixture(true);
        for ($i = 0; $i < 7; ++$i) {
            $this->persistOrder($user, $product, sprintf('SW-PROFILE-%s-%d', $user->getId(), $i), new \DateTimeImmutable("2026-08-{$this->day($i)} 12:00:00"));
        }
        $client->loginUser($user);
        $crawler = $client->request('GET', '/profil');

        self::assertResponseIsSuccessful();
        self::assertCount(5, $crawler->filter('.order-summary'));
        self::assertStringContainsString('-6', $crawler->filter('.order-summary')->first()->text());
        self::assertSelectorTextContains('body', 'Terminée (simulation)');
        self::assertSelectorTextContains('body', 'Voir toutes mes commandes');
    }

    public function testListOnlyContainsCurrentUsersOrdersInDescendingOrder(): void
    {
        $client = static::createClient();
        [$product, $userA] = $this->createCatalogFixture(true);
        [, $userB] = $this->createCatalogFixture(true);
        $older = 'SW-LIST-OLD-'.$userA->getId();
        $newer = 'SW-LIST-NEW-'.$userA->getId();
        $foreign = 'SW-LIST-FOREIGN-'.$userB->getId();
        $this->persistOrder($userA, $product, $older, new \DateTimeImmutable('2026-08-20'));
        $this->persistOrder($userA, $product, $newer, new \DateTimeImmutable('2026-08-22'));
        $this->persistOrder($userB, $product, $foreign, new \DateTimeImmutable('2026-08-23'));
        $client->loginUser($userA);
        $crawler = $client->request('GET', '/profil/commandes');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.order-summary'));
        self::assertSame($newer, $crawler->filter('.order-summary')->eq(0)->attr('data-order-reference'));
        self::assertSame($older, $crawler->filter('.order-summary')->eq(1)->attr('data-order-reference'));
        self::assertStringNotContainsString($foreign, $crawler->text());
        self::assertSelectorTextContains('body', 'Terminée (simulation)');
    }

    public function testUserCanSeeOwnOrderButAnotherUserGetsNotFound(): void
    {
        $client = static::createClient();
        [$product, $userA] = $this->createCatalogFixture(true);
        [, $userB] = $this->createCatalogFixture(true);
        $reference = 'SW-SECURITY-'.$userA->getId();
        $this->persistOrder($userA, $product, $reference, new \DateTimeImmutable());

        $client->loginUser($userA);
        $client->request('GET', '/profil/commandes/'.$reference);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $reference);
        self::assertSelectorTextContains('body', 'Terminée (simulation)');

        $client->loginUser($userB);
        $client->request('GET', '/profil/commandes/'.$reference);
        self::assertResponseStatusCodeSame(404);
    }

    /** @return array{Product, User|null} */
    private function createCatalogFixture(bool $withUser = false): array
    {
        $suffix = bin2hex(random_bytes(5));
        $category = (new Category())->setName('Catégorie '.$suffix)->setSlug('cat-'.$suffix);
        $product = (new Product())->setName('Produit '.$suffix)->setSlug('product-'.$suffix)->setDescription('Fixture')->setPriceCents(1490)->setStock(5)->setCategory($category);
        $user = $withUser ? (new User())->setEmail("customer-$suffix@shopwho.local")->setPassword('unused') : null;
        $em = $this->em();
        $em->persist($category);
        $em->persist($product);
        if ($user) { $em->persist($user); }
        $em->flush();

        return [$product, $user];
    }

    private function persistOrder(User $user, Product $product, string $reference, \DateTimeImmutable $orderedAt): Order
    {
        $order = new Order($user, $reference, $product->getPriceCents(), $orderedAt);
        new OrderItem($order, $product, 1);
        $this->em()->persist($order);
        $this->em()->flush();

        return $order;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function day(int $offset): string
    {
        return str_pad((string) (10 + $offset), 2, '0', STR_PAD_LEFT);
    }
}
