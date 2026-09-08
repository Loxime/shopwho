<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class ProductPageRedesignTest extends WebTestCase
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
            ->get(EntityManagerInterface::class)
            ->getConnection();

        $connection->executeStatement(
            "
                DELETE FROM product
                WHERE slug LIKE 'product-redesign-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM category
                WHERE slug LIKE 'product-redesign-cat-%'
            "
        );

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testProductPageDisplaysRedesignedPurchasePanel(): void
    {
        $client = static::createClient();

        $product = $this->createProduct(
            stock: 7
        );

        $crawler = $client->request(
            'GET',
            '/produit/'.$product->getSlug()
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '.product-show'
        );

        self::assertSelectorExists(
            '.product-layout.product-show-layout'
        );

        self::assertSelectorTextContains(
            '.product-purchase-panel h1',
            $product->getName()
        );

        self::assertSelectorTextContains(
            '.product-main-description',
            'Description produit redesign'
        );

        self::assertSelectorTextContains(
            '.product-stock.is-in-stock',
            'En stock'
        );

        self::assertSelectorTextContains(
            '.product-stock.is-in-stock',
            '7 unités disponibles'
        );

        self::assertSelectorExists(
            sprintf(
                'form[action="/panier/ajouter/%d"]',
                $product->getId()
            )
        );

        self::assertSelectorTextContains(
            '.product-add-cart',
            'Ajouter au panier'
        );

        self::assertSelectorTextContains(
            '.product-purchase-reassurance',
            'Aucun paiement réel'
        );

        self::assertSame(
            '/connexion',
            $crawler
                ->filter(
                    '.favorite-button'
                )
                ->attr('href')
        );
    }

    public function testOutOfStockProductCannotBeAddedFromProductPage(): void
    {
        $client = static::createClient();

        $product = $this->createProduct(
            stock: 0
        );

        $client->request(
            'GET',
            '/produit/'.$product->getSlug()
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.product-stock.is-out-of-stock',
            'Rupture de stock'
        );

        self::assertSelectorNotExists(
            sprintf(
                'form[action="/panier/ajouter/%d"]',
                $product->getId()
            )
        );

        self::assertSelectorExists(
            '.product-add-cart[disabled]'
        );

        self::assertSelectorTextContains(
            '.product-add-cart',
            'Indisponible'
        );
    }

    public function testProductPageDisplaysReviewsSectionAndBreadcrumb(): void
    {
        $client = static::createClient();

        $product = $this->createProduct(
            stock: 3
        );

        $client->request(
            'GET',
            '/produit/'.$product->getSlug()
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '.product-breadcrumb'
        );

        self::assertSelectorTextContains(
            '.product-breadcrumb',
            $product->getCategory()->getName()
        );

        self::assertSelectorExists(
            '#product-reviews'
        );

        self::assertSelectorTextContains(
            '#product-reviews h2',
            'Avis clients'
        );

        self::assertSelectorTextContains(
            '.product-reviews-empty',
            'Aucun avis client pour le moment'
        );

        self::assertSelectorTextContains(
            '.review-auth-callout',
            'Vous souhaitez donner votre avis'
        );
    }

    private function createProduct(
        int $stock
    ): Product {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $category = (new Category())
            ->setName(
                'Catégorie redesign '.$suffix
            )
            ->setSlug(
                'product-redesign-cat-'.$suffix
            );

        $product = (new Product())
            ->setName(
                'Produit redesign '.$suffix
            )
            ->setSlug(
                'product-redesign-'.$suffix
            )
            ->setDescription(
                'Description produit redesign'
            )
            ->setPriceCents(4990)
            ->setStock($stock)
            ->setCategory($category);

        $em = static::getContainer()
            ->get(EntityManagerInterface::class);

        $em->persist($category);
        $em->persist($product);
        $em->flush();

        return $product;
    }
}
