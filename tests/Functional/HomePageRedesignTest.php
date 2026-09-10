<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\SpecialOffer;
use App\Enum\SpecialOfferPlacement;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class HomePageRedesignTest extends WebTestCase
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
                DELETE FROM special_offer
                WHERE title LIKE 'Accueil redesign %'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM product
                WHERE slug LIKE 'home-redesign-product-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM category
                WHERE slug LIKE 'home-redesign-category-%'
            "
        );

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testHomepageDisplaysRedesignedHeroAndCatalog(): void
    {
        $client = static::createClient();

        $product = $this->createProduct();

        $crawler = $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '.hero.home-hero'
        );

        self::assertSelectorTextContains(
            '.home-hero h1',
            'Découvrez ce qui vous ressemble.'
        );

        self::assertSelectorExists(
            '.home-hero-panel'
        );

        self::assertSelectorCount(
            3,
            '.home-hero-feature-icon'
        );

        self::assertStringNotContainsString(
            'E-commerce expérimental & data',
            $crawler->filter('.home-hero')->text()
        );

        self::assertSelectorTextContains(
            '.home-hero-reassurance',
            'Aucun paiement réel'
        );

        self::assertSame(
            '#catalogue',
            $crawler
                ->filter(
                    '.home-hero-actions '
                    .'a.btn.orange'
                )
                ->attr('href')
        );

        self::assertSelectorExists(
            '#catalogue.catalog-section'
        );

        self::assertSelectorTextContains(
            '.catalog-heading h2',
            'Nos produits'
        );

        self::assertSelectorExists(
            '.catalog-grid'
        );

        self::assertStringContainsString(
            $product->getName(),
            $crawler->text()
        );
    }

    public function testHomepageDisplaysPrimaryAndSecondaryOffers(): void
    {
        $client = static::createClient();

        for ($index = 0; $index < 4; ++$index) {
            $this->createOffer(
                $index,
                100 - $index
            );
        }

        $crawler = $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '#special-offers.home-offers-section'
        );

        self::assertSelectorCount(
            3,
            '.special-offers-primary '
            .'.special-offer-card'
        );

        self::assertSelectorCount(
            1,
            '.special-offers-secondary '
            .'.special-offer-card'
        );

        self::assertSelectorCount(
            4,
            '[data-special-offer]'
        );

        $first = $crawler->filter(
            '.special-offers-primary '
            .'.special-offer-card'
        )->first();

        self::assertStringContainsString(
            '--offer-bg: #272785',
            (string) $first->attr('style')
        );

        self::assertSelectorTextContains(
            '.special-offers-primary '
            .'.special-offer-card:first-child',
            'Accueil redesign 0'
        );

        self::assertSelectorExists(
            '.js-special-offer-link'
        );
    }

    private function createProduct(): Product
    {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $category = (new Category())
            ->setName(
                'Accueil catégorie '.$suffix
            )
            ->setSlug(
                'home-redesign-category-'.$suffix
            );

        $product = (new Product())
            ->setName(
                'Accueil produit '.$suffix
            )
            ->setSlug(
                'home-redesign-product-'.$suffix
            )
            ->setDescription(
                'Produit utilisé pour tester '
                .'le redesign de la page accueil.'
            )
            ->setPriceCents(2590)
            ->setStock(8)
            ->setCategory($category);

        $em = $this->em();

        $em->persist($category);
        $em->persist($product);
        $em->flush();

        return $product;
    }

    private function createOffer(
        int $index,
        int $priority
    ): SpecialOffer {
        $offer = (new SpecialOffer())
            ->setTitle(
                'Accueil redesign '.$index
            )
            ->setContent(
                'Contenu de présentation '
                .'de l’offre '.$index.'.'
            )
            ->setCtaLabel(
                'Découvrir'
            )
            ->setTargetUrl(
                '/?campaign=home-redesign-'.$index
            )
            ->setPlacement(
                SpecialOfferPlacement::Homepage
            )
            ->setBackgroundColor(
                '#272785'
            )
            ->setTextColor(
                '#FFFFFF'
            )
            ->setPriority(
                $priority
            );

        $this->em()->persist(
            $offer
        );

        $this->em()->flush();

        return $offer;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(
            EntityManagerInterface::class
        );
    }
}
