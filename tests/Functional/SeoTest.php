<?php

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Product;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class SeoTest extends WebTestCase
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
                DELETE FROM article
                WHERE slug LIKE 'seo-article-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM product
                WHERE slug LIKE 'seo-product-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM category
                WHERE slug LIKE 'seo-category-%'
            "
        );

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testHomepageProvidesSeoMetadata(): void
    {
        $client = static::createClient();

        $crawler = $client->request(
            'GET',
            '/?q=casque'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            'meta[name="description"]'
        );

        self::assertSame(
            'http://localhost/',
            $crawler
                ->filter(
                    'link[rel="canonical"]'
                )
                ->attr('href')
        );

        self::assertSame(
            'noindex,follow',
            trim(
                (string) $crawler
                    ->filter(
                        'meta[name="robots"]'
                    )
                    ->attr('content')
            )
        );

        self::assertSelectorExists(
            'meta[property="og:title"]'
        );

        self::assertSelectorExists(
            'meta[property="og:url"]'
        );
    }

    public function testProductProvidesCanonicalAndStructuredData(): void
    {
        $client = static::createClient();

        $product = $this->createProduct();

        $crawler = $client->request(
            'GET',
            '/produit/'.$product->getSlug()
        );

        self::assertResponseIsSuccessful();

        $expectedUrl =
            'http://localhost/produit/'
            .$product->getSlug();

        self::assertSame(
            $expectedUrl,
            $crawler
                ->filter(
                    'link[rel="canonical"]'
                )
                ->attr('href')
        );

        self::assertSame(
            'product',
            trim(
                (string) $crawler
                    ->filter(
                        'meta[property="og:type"]'
                    )
                    ->attr('content')
            )
        );

        $json = $crawler
            ->filter(
                'script[type="application/ld+json"]'
            )
            ->text();

        $schema = json_decode(
            $json,
            true,
            flags: JSON_THROW_ON_ERROR
        );

        self::assertSame(
            'https://schema.org',
            $schema['@context']
        );

        self::assertSame(
            'Product',
            $schema['@type']
        );

        self::assertSame(
            $product->getName(),
            $schema['name']
        );

        self::assertSame(
            $expectedUrl,
            $schema['url']
        );

        self::assertArrayNotHasKey(
            'offers',
            $schema
        );
    }

    public function testPrivateStorefrontPagesAreNoindex(): void
    {
        $client = static::createClient();

        $crawler = $client->request(
            'GET',
            '/panier'
        );

        self::assertResponseIsSuccessful();

        self::assertSame(
            'noindex,nofollow',
            trim(
                (string) $crawler
                    ->filter(
                        'meta[name="robots"]'
                    )
                    ->attr('content')
            )
        );
    }

    public function testRobotsFileProtectsPrivateAreas(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/robots.txt'
        );

        self::assertResponseIsSuccessful();

        $content =
            (string) $client
                ->getResponse()
                ->getContent();

        self::assertStringContainsString(
            'User-agent: *',
            $content
        );

        self::assertStringContainsString(
            'Disallow: /admin',
            $content
        );

        self::assertStringContainsString(
            'Disallow: /profil',
            $content
        );

        self::assertStringContainsString(
            'Sitemap: http://localhost/sitemap.xml',
            $content
        );
    }

    public function testSitemapContainsActiveProductAndCategory(): void
    {
        $client = static::createClient();

        $product = $this->createProduct();

        $client->request(
            'GET',
            '/sitemap.xml'
        );

        self::assertResponseIsSuccessful();

        $content =
            (string) $client
                ->getResponse()
                ->getContent();

        self::assertStringContainsString(
            '/produit/'.$product->getSlug(),
            $content
        );

        self::assertStringContainsString(
            '?category='
            .$product
                ->getCategory()
                ->getSlug(),
            $content
        );
    }

    public function testSitemapContainsOnlyPublishedArticles(): void
    {
        $client = static::createClient();

        $suffix = bin2hex(
            random_bytes(6)
        );

        $published = (new Article())
            ->setTitle(
                'Article publié '.$suffix
            )
            ->setSlug(
                'seo-article-published-'.$suffix
            )
            ->setExcerpt(
                'Résumé article publié.'
            )
            ->setContent(
                'Contenu article publié.'
            )
            ->setIsPublished(true)
            ->setPublishedAt(
                new \DateTimeImmutable(
                    '-1 hour'
                )
            );

        $draft = (new Article())
            ->setTitle(
                'Article brouillon '.$suffix
            )
            ->setSlug(
                'seo-article-draft-'.$suffix
            )
            ->setExcerpt(
                'Résumé brouillon.'
            )
            ->setContent(
                'Contenu brouillon.'
            )
            ->setIsPublished(false);

        $em = static::getContainer()
            ->get(EntityManagerInterface::class);

        $em->persist($published);
        $em->persist($draft);
        $em->flush();

        $client->request(
            'GET',
            '/sitemap.xml'
        );

        self::assertResponseIsSuccessful();

        $content =
            (string) $client
                ->getResponse()
                ->getContent();

        self::assertStringContainsString(
            '/articles/'
            .$published->getSlug(),
            $content
        );

        self::assertStringNotContainsString(
            '/articles/'
            .$draft->getSlug(),
            $content
        );
    }

    private function createProduct(): Product
    {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $category = (new Category())
            ->setName(
                'SEO '.$suffix
            )
            ->setSlug(
                'seo-category-'.$suffix
            );

        $product = (new Product())
            ->setName(
                'Produit SEO '.$suffix
            )
            ->setSlug(
                'seo-product-'.$suffix
            )
            ->setDescription(
                'Description destinée au test '
                .'des métadonnées SEO Shopwho.'
            )
            ->setPriceCents(3990)
            ->setStock(5)
            ->setCategory($category);

        $em = static::getContainer()
            ->get(EntityManagerInterface::class);

        $em->persist($category);
        $em->persist($product);
        $em->flush();

        return $product;
    }
}
