<?php

namespace App\Tests\Functional;

use App\Entity\Article;
use App\Entity\User;
use App\Kernel;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class ArticleFeatureTest extends WebTestCase
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
                WHERE slug LIKE
                    'article-feature-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM app_user
                WHERE email LIKE
                    'article-feature-%@shopwho.test'
            "
        );

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testDraftArticleIsNotPubliclyAvailable(): void
    {
        $client = static::createClient();

        $article = $this->createArticle(
            'draft',
            false
        );

        $client->request(
            'GET',
            '/articles/'.$article->getSlug()
        );

        self::assertResponseStatusCodeSame(
            404
        );
    }

    public function testPublishedArticleAppearsOnIndexAndDetailPage(): void
    {
        $client = static::createClient();

        $article = $this->createArticle(
            'published',
            true,
            new \DateTimeImmutable(
                '-5 minutes'
            )
        );

        $client->request(
            'GET',
            '/articles'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.editorial-grid',
            $article->getTitle()
        );

        $crawler = $client->request(
            'GET',
            '/articles/'.$article->getSlug()
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.editorial-article h1',
            $article->getTitle()
        );

        self::assertSame(
            'http://localhost/articles/'
            .$article->getSlug(),
            $crawler
                ->filter(
                    'link[rel="canonical"]'
                )
                ->attr('href')
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
            'Article',
            $schema['@type']
        );

        self::assertSame(
            $article->getTitle(),
            $schema['headline']
        );
    }

    public function testFutureArticleIsNotVisibleYet(): void
    {
        $client = static::createClient();

        $article = $this->createArticle(
            'scheduled',
            true,
            new \DateTimeImmutable(
                '+1 day'
            )
        );

        $client->request(
            'GET',
            '/articles/'.$article->getSlug()
        );

        self::assertResponseStatusCodeSame(
            404
        );

        $client->request(
            'GET',
            '/articles'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextNotContains(
            '.editorial-grid',
            $article->getTitle()
        );
    }

    public function testMarketingManagerCanCreatePublishedArticle(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createUser(
                ['ROLE_MARKETING_MANAGER']
            )
        );

        $crawler = $client->request(
            'GET',
            '/admin/articles/new'
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton(
                'Enregistrer'
            )
            ->form();

        $client->submit(
            $form,
            [
                'article[title]' =>
                    'Article Feature Création',
                'article[slug]' => '',
                'article[excerpt]' =>
                    'Résumé Article Feature.',
                'article[content]' =>
                    'Contenu Article Feature.',
                'article[coverImageUrl]' =>
                    '',
                'article[isPublished]' =>
                    '1',
                'article[publishedAt]' =>
                    '',
            ]
        );

        self::assertResponseRedirects(
            '/admin/articles'
        );

        $article = static::getContainer()
            ->get(ArticleRepository::class)
            ->findOneBy([
                'slug' =>
                    'article-feature-creation',
            ]);

        self::assertNotNull(
            $article
        );

        self::assertTrue(
            $article->isPublished()
        );

        self::assertNotNull(
            $article->getPublishedAt()
        );
    }

    public function testCustomerCannotAccessArticleAdministration(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createUser(
                ['ROLE_USER']
            )
        );

        $client->request(
            'GET',
            '/admin/articles'
        );

        self::assertResponseStatusCodeSame(
            403
        );
    }

    public function testSitemapOnlyContainsVisibleArticles(): void
    {
        $client = static::createClient();

        $published = $this->createArticle(
            'sitemap-visible',
            true,
            new \DateTimeImmutable(
                '-1 hour'
            )
        );

        $draft = $this->createArticle(
            'sitemap-draft',
            false
        );

        $future = $this->createArticle(
            'sitemap-future',
            true,
            new \DateTimeImmutable(
                '+2 days'
            )
        );

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

        self::assertStringNotContainsString(
            '/articles/'
            .$future->getSlug(),
            $content
        );
    }

    private function createArticle(
        string $suffix,
        bool $published,
        ?\DateTimeImmutable $publishedAt = null
    ): Article {
        $article = (new Article())
            ->setTitle(
                'Article Feature '.$suffix
            )
            ->setSlug(
                'article-feature-'.$suffix
            )
            ->setExcerpt(
                'Résumé de test '.$suffix.'.'
            )
            ->setContent(
                'Contenu de test '.$suffix.'.'
            )
            ->setIsPublished(
                $published
            )
            ->setPublishedAt(
                $publishedAt
            );

        $em = static::getContainer()
            ->get(EntityManagerInterface::class);

        $em->persist($article);
        $em->flush();

        return $article;
    }

    private function createUser(
        array $roles
    ): User {
        $user = (new User())
            ->setEmail(
                'article-feature-'
                .bin2hex(random_bytes(6))
                .'@shopwho.test'
            )
            ->setFirstName('Article')
            ->setLastName('Tester')
            ->setPassword('unused')
            ->setRoles($roles);

        $em = static::getContainer()
            ->get(EntityManagerInterface::class);

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
