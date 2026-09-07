<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
