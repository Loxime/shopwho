<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use App\Kernel;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class CatalogUxQuickWinsTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
        );
    }

    protected function tearDown(): void
    {
        if (!static::$booted) {
            static::bootKernel();
        }

        $connection = $this->em()->getConnection();

        $connection->executeStatement(
            "
                DELETE FROM product_review
                WHERE product_id IN (
                    SELECT id
                    FROM product
                    WHERE slug LIKE 'ux-test-%'
                )
            "
        );

        $connection->executeStatement(
            "DELETE FROM product WHERE slug LIKE 'ux-test-%'"
        );

        $connection->executeStatement(
            "DELETE FROM category WHERE slug LIKE 'ux-test-%'"
        );

        $connection->executeStatement(
            "DELETE FROM app_user WHERE email LIKE 'ux-test-%@example.test'"
        );

        $this->em()->clear();

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testCatalogDisplaysRatingAndReviewCount(): void
    {
        $client = static::createClient();

        [$product, $userA] = $this->fixture();
        $userB = $this->createUser();

        $this->persistReview($userA, $product, 5);
        $this->persistReview($userB, $product, 4);

        $client->request(
            'GET',
            '/?q='.urlencode($product->getName())
        );

        self::assertResponseIsSuccessful();

        $stars = $client
            ->getCrawler()
            ->filter('#catalogue .stars')
            ->text();

        $stars = preg_replace(
            '/\s+/u',
            '',
            $stars
        );

        self::assertSame(
            '★★★★★',
            $stars
        );
    }

    public function testCatalogDisplaysNoReviewMessage(): void
    {
        $client = static::createClient();

        [$product] = $this->fixture();

        $client->request(
            'GET',
            '/?q='.urlencode($product->getName())
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '#catalogue .card-rating',
            'Pas encore d’avis'
        );
    }

    public function testCatalogDisplaysTruncatedDescription(): void
    {
        $client = static::createClient();

        $description = str_repeat(
            'Description produit UX détaillée. ',
            4
        );

        [$product] = $this->fixture($description);

        $client->request(
            'GET',
            '/?q='.urlencode($product->getName())
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '#catalogue .card-description',
            substr($description, 0, 60)
        );

        self::assertSelectorTextContains(
            '#catalogue .card-description',
            '…'
        );
    }

    public function testCatalogDisplaysDedicatedEmptySearchState(): void
    {
        $client = static::createClient();

        $query = 'ux-no-result-'.bin2hex(
            random_bytes(8)
        );

        $client->request(
            'GET',
            '/?q='.urlencode($query)
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorCount(
            0,
            '#catalogue .js-product-card'
        );

        self::assertSelectorExists(
            '#catalogue .catalog-empty-state'
        );

        self::assertSelectorTextContains(
            '#catalogue .catalog-empty-state',
            'Aucun résultat pour « '.$query.' »'
        );

        self::assertSelectorTextContains(
            '#catalogue .catalog-empty-state',
            'Aucun produit du catalogue ne correspond'
        );
    }

    public function testEmptySearchOffersSearchResetAction(): void
    {
        $client = static::createClient();

        $query = 'ux-empty-reset-'.bin2hex(
            random_bytes(8)
        );

        $crawler = $client->request(
            'GET',
            '/?q='.urlencode($query)
        );

        self::assertResponseIsSuccessful();

        $reset = $crawler->filter(
            '#catalogue [data-empty-search-reset]'
        );

        self::assertCount(
            1,
            $reset
        );

        self::assertStringContainsString(
            'Effacer la recherche',
            $reset->text()
        );

        self::assertSame(
            '/',
            $reset->attr('href')
        );

        self::assertSelectorCount(
            0,
            '#catalogue [data-empty-catalog-reset]'
        );
    }

    public function testClearingSearchPreservesSelectedCategory(): void
    {
        $client = static::createClient();

        $suffix = bin2hex(
            random_bytes(8)
        );

        $category = (new Category())
            ->setName(
                'UX Empty '.$suffix
            )
            ->setSlug(
                'ux-test-empty-category-'.$suffix
            );

        $this->em()->persist($category);
        $this->em()->flush();

        $query = 'ux-no-result-'.$suffix;

        $crawler = $client->request(
            'GET',
            '/?q='.urlencode($query)
            .'&category='.urlencode(
                $category->getSlug()
            )
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '#catalogue .catalog-empty-state'
        );

        $searchReset = $crawler->filter(
            '#catalogue [data-empty-search-reset]'
        );

        self::assertCount(
            1,
            $searchReset
        );

        self::assertSame(
            '/?category='.urlencode(
                $category->getSlug()
            ),
            $searchReset->attr('href')
        );

        $catalogReset = $crawler->filter(
            '#catalogue [data-empty-catalog-reset]'
        );

        self::assertCount(
            1,
            $catalogReset
        );

        self::assertStringContainsString(
            'Voir tous les produits',
            $catalogReset->text()
        );

        self::assertSame(
            '/',
            $catalogReset->attr('href')
        );
    }

    public function testProductPageDoesNotExposeStock(): void
    {
        $client = static::createClient();

        [$product] = $this->fixture();

        $product->setStock(37);
        $this->em()->flush();

        $client->request(
            'GET',
            '/produit/'.$product->getSlug()
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextNotContains(
            '.product-layout',
            'Stock :'
        );

        self::assertSelectorTextContains(
            '.product-layout',
            $product->getName()
        );
    }

    public function testStorefrontDeclaresFavicon(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            'link[rel="icon"][href="/favicon.svg"][type="image/svg+xml"]'
        );
    }

    public function testRatingRepositoryAggregatesSeveralProducts(): void
    {
        self::bootKernel();

        [$productA, $userA] = $this->fixture();
        [$productB, $userB] = $this->fixture();

        $userC = $this->createUser();

        $this->persistReview($userA, $productA, 5);
        $this->persistReview($userC, $productA, 3);

        $this->persistReview($userB, $productB, 4);

        $stats = $this->reviews()->getRatingStatsByProductIds([
            $productA->getId(),
            $productB->getId(),
        ]);

        self::assertSame(
            [
                'average' => 4.0,
                'count' => 2,
            ],
            $stats[$productA->getId()]
        );

        self::assertSame(
            [
                'average' => 4.0,
                'count' => 1,
            ],
            $stats[$productB->getId()]
        );
    }

    /**
     * @return array{Product, User}
     */
    private function fixture(
        ?string $description = null
    ): array {
        $suffix = bin2hex(random_bytes(6));

        $category = (new Category())
            ->setName('UX '.$suffix)
            ->setSlug('ux-test-category-'.$suffix);

        $product = (new Product())
            ->setName('UX Product '.$suffix)
            ->setSlug('ux-test-product-'.$suffix)
            ->setDescription(
                $description
                ?? 'Produit utilisé pour les tests UX du catalogue.'
            )
            ->setPriceCents(1999)
            ->setStock(12)
            ->setCategory($category);

        $user = $this->createUser();

        $this->em()->persist($category);
        $this->em()->persist($product);
        $this->em()->flush();

        return [$product, $user];
    }

    private function createUser(): User
    {
        $suffix = bin2hex(random_bytes(6));

        $user = (new User())
            ->setEmail(
                'ux-test-'.$suffix.'@example.test'
            )
            ->setFirstName('Test')
            ->setLastName('UX')
            ->setPassword('unused');

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function persistReview(
        User $user,
        Product $product,
        int $rating
    ): Review {
        $review = new Review(
            $user,
            $product,
            $rating
        );

        $this->em()->persist($review);
        $this->em()->flush();

        return $review;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(
            EntityManagerInterface::class
        );
    }

    private function reviews(): ReviewRepository
    {
        return static::getContainer()->get(
            ReviewRepository::class
        );
    }
}
