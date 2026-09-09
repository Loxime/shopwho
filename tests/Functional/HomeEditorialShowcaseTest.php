<?php

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Entity\Partner;
use App\Entity\SpecialOffer;
use App\Enum\SpecialOfferPlacement;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class HomeEditorialShowcaseTest extends WebTestCase
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
                WHERE title LIKE
                    'Home showcase %'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM article
                WHERE slug LIKE
                    'home-showcase-article-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM partner
                WHERE name LIKE
                    'Home showcase partner %'
            "
        );

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testHomepageDisplaysLatestEditorialContent(): void
    {
        $client = static::createClient();

        for ($index = 0; $index < 4; ++$index) {
            $this->createArticle(
                'published-'.$index,
                true,
                new \DateTimeImmutable(
                    sprintf(
                        '-%d hours',
                        $index + 1
                    )
                )
            );
        }

        $draft = $this->createArticle(
            'draft',
            false
        );

        $future = $this->createArticle(
            'future',
            true,
            new \DateTimeImmutable(
                '+1 day'
            )
        );

        for ($index = 0; $index < 7; ++$index) {
            $this->createPartner(
                'active-'.$index,
                true,
                100 - $index
            );
        }

        $inactive = $this->createPartner(
            'inactive',
            false,
            999
        );

        $crawler = $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorCount(
            3,
            '.home-editorial-card'
        );

        self::assertSelectorTextContains(
            '.home-editorial-section',
            'Home showcase article published-0'
        );

        self::assertSelectorTextNotContains(
            '.home-editorial-section',
            'Home showcase article published-3'
        );

        self::assertSelectorTextNotContains(
            '.home-editorial-section',
            $draft->getTitle()
        );

        self::assertSelectorTextNotContains(
            '.home-editorial-section',
            $future->getTitle()
        );

        self::assertSelectorCount(
            6,
            '.home-partner-card'
        );

        self::assertSelectorTextContains(
            '.home-partners-section',
            'Home showcase partner active-0'
        );

        self::assertSelectorTextNotContains(
            '.home-partners-section',
            'Home showcase partner active-6'
        );

        self::assertSelectorTextNotContains(
            '.home-partners-section',
            $inactive->getName()
        );

        $firstPartner = $crawler
            ->filter(
                '.home-partner-card strong'
            )
            ->first()
            ->text();

        self::assertSame(
            'Home showcase partner active-0',
            trim($firstPartner)
        );
    }

    public function testEditorialShowcaseIsHiddenOnSearchResults(): void
    {
        $client = static::createClient();

        $this->createArticle(
            'search-hidden',
            true,
            new \DateTimeImmutable(
                '-1 hour'
            )
        );

        $this->createPartner(
            'search-hidden',
            true,
            100
        );

        $client->request(
            'GET',
            '/?q=shopwho'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists(
            '.home-editorial-section'
        );

        self::assertSelectorNotExists(
            '.home-partners-section'
        );
    }

    public function testHeaderOfferDoesNotPolluteHomepageOfferGrid(): void
    {
        $client = static::createClient();

        $header = $this->createOffer(
            'Header only',
            SpecialOfferPlacement::Header,
            500
        );

        $homepage = $this->createOffer(
            'Homepage only',
            SpecialOfferPlacement::Homepage,
            400
        );

        $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.special-offer-banner[data-placement="header"]',
            $header->getTitle()
        );

        self::assertSelectorTextContains(
            '#special-offers',
            $homepage->getTitle()
        );

        self::assertSelectorTextNotContains(
            '#special-offers',
            $header->getTitle()
        );
    }

    public function testBothOfferAppearsInHeaderAndHomepage(): void
    {
        $client = static::createClient();

        $offer = $this->createOffer(
            'Both',
            SpecialOfferPlacement::Both,
            900
        );

        $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.special-offer-banner[data-placement="header"]',
            $offer->getTitle()
        );

        self::assertSelectorTextContains(
            '#special-offers',
            $offer->getTitle()
        );
    }

    private function createArticle(
        string $suffix,
        bool $published,
        ?\DateTimeImmutable $publishedAt = null
    ): Article {
        $article = (new Article())
            ->setTitle(
                'Home showcase article '.$suffix
            )
            ->setSlug(
                'home-showcase-article-'.$suffix
            )
            ->setExcerpt(
                'Résumé homepage '.$suffix.'.'
            )
            ->setContent(
                'Contenu homepage '.$suffix.'.'
            )
            ->setIsPublished(
                $published
            )
            ->setPublishedAt(
                $publishedAt
            );

        $this->em()->persist(
            $article
        );

        $this->em()->flush();

        return $article;
    }

    private function createPartner(
        string $suffix,
        bool $active,
        int $priority
    ): Partner {
        $partner = (new Partner())
            ->setName(
                'Home showcase partner '.$suffix
            )
            ->setDescription(
                'Partenaire homepage '.$suffix.'.'
            )
            ->setWebsiteUrl(
                'https://example.com/'
                .$suffix
            )
            ->setIsActive(
                $active
            )
            ->setPriority(
                $priority
            );

        $this->em()->persist(
            $partner
        );

        $this->em()->flush();

        return $partner;
    }

    private function createOffer(
        string $suffix,
        SpecialOfferPlacement $placement,
        int $priority
    ): SpecialOffer {
        $offer = (new SpecialOffer())
            ->setTitle(
                'Home showcase '.$suffix
            )
            ->setContent(
                'Campagne homepage '.$suffix.'.'
            )
            ->setCtaLabel(
                'Découvrir'
            )
            ->setTargetUrl(
                '/?campaign=home-showcase'
            )
            ->setPlacement(
                $placement
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
