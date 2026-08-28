<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use App\Kernel;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class AdminReviewManagementTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private Connection $connection;

    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $this->em = $em;
        $this->connection = $em->getConnection();

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->em->clear();

        parent::tearDown();
    }

    public function testAnonymousUserCannotAccessAdminReviews(): void
    {
        $this->client->request(
            'GET',
            '/admin/reviews'
        );

        self::assertResponseRedirects(
            '/connexion'
        );
    }

    public function testAdminCanSearchReviews(): void
    {
        $admin = $this->createUser(
            ['ROLE_ADMIN']
        );

        $marker = 'Search-'.bin2hex(
            random_bytes(5)
        );

        $matchingProduct = $this->createProduct(
            $marker.' Alpha'
        );

        $otherProduct = $this->createProduct(
            'Produit autre '.bin2hex(
                random_bytes(5)
            )
        );

        $customer = $this->createUser();

        $this->createReview(
            $customer,
            $matchingProduct,
            5,
            'Excellent produit'
        );

        $this->createReview(
            $customer,
            $otherProduct,
            2,
            'Avis sans rapport'
        );

        $this->client->loginUser($admin);

        $crawler = $this->client->request(
            'GET',
            '/admin/reviews?q='.urlencode($marker)
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'h1',
            'Avis clients'
        );

        self::assertStringContainsString(
            $matchingProduct->getName(),
            $crawler->text()
        );

        self::assertStringNotContainsString(
            $otherProduct->getName(),
            $crawler->text()
        );

        self::assertCount(
            1,
            $crawler->filter(
                '.admin-table tbody tr'
            )
        );
    }

    public function testAdminCanFilterByRatingAndSource(): void
    {
        $admin = $this->createUser(
            ['ROLE_ADMIN']
        );

        $marker = 'Filter-'.bin2hex(
            random_bytes(5)
        );

        $customer = $this->createUser();

        $nativeProduct = $this->createProduct(
            $marker.' Native'
        );

        $importedProduct = $this->createProduct(
            $marker.' Imported'
        );

        $otherProduct = $this->createProduct(
            $marker.' Other'
        );

        $this->createReview(
            $customer,
            $nativeProduct,
            5,
            'Native cinq étoiles'
        );

        $this->createReview(
            $customer,
            $importedProduct,
            5,
            'Importé cinq étoiles',
            'REVIEW-'.bin2hex(
                random_bytes(5)
            )
        );

        $this->createReview(
            $customer,
            $otherProduct,
            3,
            'Native trois étoiles'
        );

        $this->client->loginUser($admin);

        $crawler = $this->client->request(
            'GET',
            '/admin/reviews?'
                .http_build_query([
                    'q' => $marker,
                    'rating' => 5,
                    'source' => 'imported',
                ])
        );

        self::assertResponseIsSuccessful();

        self::assertStringContainsString(
            $importedProduct->getName(),
            $crawler->text()
        );

        self::assertStringNotContainsString(
            $nativeProduct->getName(),
            $crawler->text()
        );

        self::assertStringNotContainsString(
            $otherProduct->getName(),
            $crawler->text()
        );

        self::assertSelectorTextContains(
            '.admin-badge-accent',
            'Importée'
        );

        self::assertCount(
            1,
            $crawler->filter(
                '.admin-table tbody tr'
            )
        );
    }

    public function testAdminReviewPaginationUsesTwentyFiveItemsPerPage(): void
    {
        $admin = $this->createUser(
            ['ROLE_ADMIN']
        );

        $customer = $this->createUser();

        $marker = 'Pagination-'.bin2hex(
            random_bytes(5)
        );

        for ($i = 1; $i <= 26; ++$i) {
            $product = $this->createProduct(
                sprintf(
                    '%s %02d',
                    $marker,
                    $i
                )
            );

            $this->createReview(
                $customer,
                $product,
                4,
                'Avis pagination '.$i
            );
        }

        $this->client->loginUser($admin);

        $crawler = $this->client->request(
            'GET',
            '/admin/reviews?'
                .http_build_query([
                    'q' => $marker,
                    'page' => 1,
                ])
        );

        self::assertResponseIsSuccessful();

        self::assertCount(
            25,
            $crawler->filter(
                '.admin-table tbody tr'
            )
        );

        self::assertSelectorTextContains(
            '.admin-pagination-summary',
            'Page 1 sur 2'
        );

        self::assertSelectorTextContains(
            '.admin-pagination-actions',
            'Suivant'
        );

        $crawler = $this->client->request(
            'GET',
            '/admin/reviews?'
                .http_build_query([
                    'q' => $marker,
                    'page' => 2,
                ])
        );

        self::assertResponseIsSuccessful();

        self::assertCount(
            1,
            $crawler->filter(
                '.admin-table tbody tr'
            )
        );

        self::assertSelectorTextContains(
            '.admin-pagination-summary',
            'Page 2 sur 2'
        );

        self::assertSelectorTextContains(
            '.admin-pagination-actions',
            'Précédent'
        );
    }

    public function testRequestedPageIsClampedToLastAvailablePage(): void
    {
        $admin = $this->createUser(
            ['ROLE_ADMIN']
        );

        $customer = $this->createUser();

        $marker = 'Clamp-'.bin2hex(
            random_bytes(5)
        );

        $product = $this->createProduct(
            $marker.' Product'
        );

        $this->createReview(
            $customer,
            $product,
            4,
            'Avis unique'
        );

        $this->client->loginUser($admin);

        $crawler = $this->client->request(
            'GET',
            '/admin/reviews?'
                .http_build_query([
                    'q' => $marker,
                    'page' => 999,
                ])
        );

        self::assertResponseIsSuccessful();

        self::assertStringContainsString(
            $product->getName(),
            $crawler->text()
        );

        self::assertCount(
            1,
            $crawler->filter(
                '.admin-table tbody tr'
            )
        );
    }

    private function createUser(
        array $roles = []
    ): User {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $user = (new User())
            ->setEmail(
                'review-admin-'.$suffix
                    .'@shopwho.local'
            )
            ->setFirstName('Test')
            ->setLastName('Review')
            ->setPassword(
                'unused-test-password'
            )
            ->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createProduct(
        string $name
    ): Product {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $category = (new Category())
            ->setName(
                'Review category '.$suffix
            )
            ->setSlug(
                'review-category-'.$suffix
            );

        $product = (new Product())
            ->setName($name)
            ->setSlug(
                'review-product-'.$suffix
            )
            ->setDescription(
                'Produit utilisé pour les tests du back-office avis.'
            )
            ->setPriceCents(1000)
            ->setStock(10)
            ->setCategory($category)
            ->setIsActive(true);

        $this->em->persist($category);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    private function createReview(
        User $user,
        Product $product,
        int $rating,
        ?string $comment,
        ?string $externalRef = null
    ): Review {
        $review = (new Review(
            $user,
            $product,
            $rating
        ))
            ->setComment($comment)
            ->setExternalRef($externalRef);

        $this->em->persist($review);
        $this->em->flush();

        return $review;
    }
}
