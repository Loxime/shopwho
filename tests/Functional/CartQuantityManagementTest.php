<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\TrackingEvent;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\KernelInterface;

final class CartQuantityManagementTest extends WebTestCase
{
    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
        );
    }

    public function testCartQuantityCanBeUpdated(): void
    {
        $client = static::createClient();
        $product = $this->createProduct(stock: 5);

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => '3',
            ]
        );

        self::assertResponseRedirects('/panier');

        $cart = $client
            ->getRequest()
            ->getSession()
            ->get('cart', []);

        self::assertSame(
            3,
            $cart[$product->getId()] ?? null
        );
    }

    public function testCartQuantityIsCappedAtProductStock(): void
    {
        $client = static::createClient();
        $product = $this->createProduct(stock: 5);

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => '99',
            ]
        );

        self::assertResponseRedirects('/panier');

        $cart = $client
            ->getRequest()
            ->getSession()
            ->get('cart', []);

        self::assertSame(
            5,
            $cart[$product->getId()] ?? null
        );
    }

    public function testZeroQuantityRemovesProductFromCart(): void
    {
        $client = static::createClient();
        $product = $this->createProduct(stock: 5);

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => '0',
            ]
        );

        self::assertResponseRedirects('/panier');

        $cart = $client
            ->getRequest()
            ->getSession()
            ->get('cart', []);

        self::assertArrayNotHasKey(
            $product->getId(),
            $cart
        );
    }

    public function testNegativeQuantityRemovesProductFromCart(): void
    {
        $client = static::createClient();
        $product = $this->createProduct(stock: 5);

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => '-1',
            ]
        );

        self::assertResponseRedirects('/panier');

        $cart = $client
            ->getRequest()
            ->getSession()
            ->get('cart', []);

        self::assertArrayNotHasKey(
            $product->getId(),
            $cart
        );
    }

    public function testInvalidQuantityKeepsCurrentCartQuantity(): void
    {
        $client = static::createClient();
        $product = $this->createProduct(stock: 5);

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => 'invalid',
            ]
        );

        self::assertResponseRedirects('/panier');

        $cart = $client
            ->getRequest()
            ->getSession()
            ->get('cart', []);

        self::assertSame(
            1,
            $cart[$product->getId()] ?? null
        );
    }

    public function testQuantityUpdateCannotCreateCartLine(): void
    {
        $client = static::createClient();
        $product = $this->createProduct(stock: 5);

        /*
         * Aucun appel à /panier/ajouter/{id}.
         */
        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => '3',
            ]
        );

        self::assertResponseRedirects('/panier');

        $cart = $client
            ->getRequest()
            ->getSession()
            ->get('cart', []);

        self::assertArrayNotHasKey(
            $product->getId(),
            $cart
        );
    }

    public function testCartPageDisplaysQuantityControls(): void
    {
        $client = static::createClient();
        $product = $this->createProduct(stock: 5);

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'GET',
            '/panier'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorCount(
            1,
            '[data-cart-quantity-controls]'
        );

        self::assertSelectorExists(
            sprintf(
                'input#cart-quantity-%d'
                . '[name="quantity"]'
                . '[type="number"]'
                . '[value="1"]'
                . '[min="0"]'
                . '[max="5"]',
                $product->getId()
            )
        );

        self::assertSelectorCount(
            3,
            sprintf(
                'form[action="/panier/quantite/%d"]',
                $product->getId()
            )
        );

        self::assertSelectorTextContains(
            'body',
            'Stock disponible : 5'
        );
    }

    public function testIncreaseButtonIsDisabledAtStockLimit(): void
    {
        $client = static::createClient();
        $product = $this->createProduct(stock: 2);

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => '2',
            ]
        );

        $client->request(
            'GET',
            '/panier'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            sprintf(
                'input#cart-quantity-%d[value="2"]',
                $product->getId()
            )
        );

        self::assertSelectorExists(
            sprintf(
                'form[action="/panier/quantite/%d"]'
                . ' input[type="hidden"][value="3"]'
                . ' + button[disabled]',
                $product->getId()
            )
        );
    }

    public function testQuantityIncrementTracksActualCartChange(): void
    {
        $client = static::createClient();

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $product = $this->createProduct(stock: 5);

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => '3',
            ]
        );

        self::assertResponseRedirects('/panier');

        $event = $this->findTrackingEvent(
            $product,
            'CART_QUANTITY_CHANGED'
        );

        self::assertNotNull($event);

        $metadata = $event->getMetadata();

        self::assertSame(
            'quantity_update',
            $metadata['source']
        );
        self::assertSame(
            'increment',
            $metadata['change_type']
        );
        self::assertSame(
            1,
            $metadata['previous_quantity']
        );
        self::assertSame(
            3,
            $metadata['new_quantity']
        );
        self::assertSame(
            3,
            $metadata['requested_quantity']
        );
        self::assertSame(
            2,
            $metadata['delta']
        );
        self::assertSame(
            1490,
            $metadata['price_cents']
        );
        self::assertSame(
            '/panier/quantite/'.$product->getId(),
            $metadata['path']
        );
        self::assertSame(
            'POST',
            $metadata['method']
        );
    }

    public function testQuantityDecrementTracksActualCartChange(): void
    {
        $client = static::createClient();

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $product = $this->createProduct(stock: 5);

        for ($i = 0; $i < 4; ++$i) {
            $client->request(
                'POST',
                '/panier/ajouter/'.$product->getId()
            );
        }

        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => '2',
            ]
        );

        self::assertResponseRedirects('/panier');

        $event = $this->findTrackingEvent(
            $product,
            'CART_QUANTITY_CHANGED'
        );

        self::assertNotNull($event);

        $metadata = $event->getMetadata();

        self::assertSame(
            'decrement',
            $metadata['change_type']
        );
        self::assertSame(
            4,
            $metadata['previous_quantity']
        );
        self::assertSame(
            2,
            $metadata['new_quantity']
        );
        self::assertSame(
            2,
            $metadata['requested_quantity']
        );
        self::assertSame(
            -2,
            $metadata['delta']
        );
    }

    public function testQuantityRemovalTracksRemoveFromCart(): void
    {
        $client = static::createClient();

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $product = $this->createProduct(stock: 5);

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => '0',
            ]
        );

        self::assertResponseRedirects('/panier');

        $event = $this->findTrackingEvent(
            $product,
            'REMOVE_FROM_CART'
        );

        self::assertNotNull($event);

        $metadata = $event->getMetadata();

        self::assertSame(
            'quantity_update',
            $metadata['source']
        );
        self::assertSame(
            'removal',
            $metadata['change_type']
        );
        self::assertSame(
            1,
            $metadata['previous_quantity']
        );
        self::assertSame(
            0,
            $metadata['new_quantity']
        );
        self::assertSame(
            0,
            $metadata['requested_quantity']
        );
        self::assertSame(
            -1,
            $metadata['delta']
        );
    }

    public function testQuantityUpdateDoesNotTrackWhenEffectiveQuantityIsUnchanged(): void
    {
        $client = static::createClient();

        $client->getCookieJar()->set(
            new Cookie(
                'shopwho_tracking_consent',
                'yes'
            )
        );

        $product = $this->createProduct(stock: 1);

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        /*
         * La demande vaut 99 mais le stock maximum est déjà atteint.
         * La quantité effective reste donc à 1.
         */
        $client->request(
            'POST',
            '/panier/quantite/'.$product->getId(),
            [
                'quantity' => '99',
            ]
        );

        self::assertResponseRedirects('/panier');

        $repository = $this
            ->entityManager()
            ->getRepository(TrackingEvent::class);

        self::assertSame(
            0,
            $repository->count([
                'eventType' => 'CART_QUANTITY_CHANGED',
                'productId' => $product->getId(),
            ])
        );

        self::assertSame(
            0,
            $repository->count([
                'eventType' => 'REMOVE_FROM_CART',
                'productId' => $product->getId(),
            ])
        );
    }

    private function findTrackingEvent(
        Product $product,
        string $eventType
    ): ?TrackingEvent {
        $event = $this
            ->entityManager()
            ->getRepository(TrackingEvent::class)
            ->findOneBy(
                [
                    'eventType' => $eventType,
                    'productId' => $product->getId(),
                ],
                [
                    'id' => 'DESC',
                ]
            );

        return $event instanceof TrackingEvent
            ? $event
            : null;
    }

    private function createProduct(
        int $stock
    ): Product {
        $suffix = bin2hex(
            random_bytes(5)
        );

        $category = (new Category())
            ->setName(
                'Catégorie panier '.$suffix
            )
            ->setSlug(
                'cart-category-'.$suffix
            );

        $product = (new Product())
            ->setName(
                'Produit panier '.$suffix
            )
            ->setSlug(
                'cart-product-'.$suffix
            )
            ->setDescription(
                'Produit de test du panier'
            )
            ->setPriceCents(1490)
            ->setStock($stock)
            ->setCategory($category);

        $entityManager = $this->entityManager();

        $entityManager->persist($category);
        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(
            EntityManagerInterface::class
        );
    }
}
