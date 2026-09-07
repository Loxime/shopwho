<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CartNavigationMediaTest extends WebTestCase
{
    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
        );
    }

    public function testCartDisplaysProductThumbnail(): void
    {
        $client = static::createClient();

        $product = $this->createProduct(
            imageUrl: 'https://example.com/product.jpg'
        );

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'GET',
            '/panier'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            sprintf(
                'a.cart-product'
                . '[href="/produit/%s"]',
                $product->getSlug()
            )
        );

        self::assertSelectorExists(
            sprintf(
                'img.cart-product-thumbnail'
                . '[src="https://example.com/product.jpg"]'
                . '[alt="%s"]',
                $product->getName()
            )
        );

        self::assertSelectorTextContains(
            '.cart-product-name',
            $product->getName()
        );
    }

    public function testCartDisplaysPlaceholderWithoutProductImage(): void
    {
        $client = static::createClient();

        $product = $this->createProduct(
            imageUrl: null
        );

        $client->request(
            'POST',
            '/panier/ajouter/'.$product->getId()
        );

        $client->request(
            'GET',
            '/panier'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '.cart-product-thumbnail-placeholder'
        );

        self::assertSelectorNotExists(
            '.cart-product-thumbnail'
        );
    }

    private function createProduct(
        ?string $imageUrl
    ): Product {
        $suffix = bin2hex(
            random_bytes(5)
        );

        $category = (new Category())
            ->setName(
                'Catégorie média '.$suffix
            )
            ->setSlug(
                'cart-media-category-'.$suffix
            );

        $product = (new Product())
            ->setName(
                'Produit média '.$suffix
            )
            ->setSlug(
                'cart-media-product-'.$suffix
            )
            ->setDescription(
                'Produit de test média du panier'
            )
            ->setPriceCents(1490)
            ->setStock(5)
            ->setImageUrl($imageUrl)
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
