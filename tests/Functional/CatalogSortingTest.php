<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CatalogSortingTest extends WebTestCase
{
    protected static function createKernel(
        array $options = []
    ): KernelInterface {
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

        $connection = $this
            ->em()
            ->getConnection();

        $connection->executeStatement(
            "
                DELETE FROM product
                WHERE slug LIKE 'catalog-sort-test-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM category
                WHERE slug LIKE 'catalog-sort-test-%'
            "
        );

        $this->em()->clear();

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testCatalogCanSortByAscendingPrice(): void
    {
        $client = static::createClient();

        [$query, $products] =
            $this->createCatalog();

        $crawler = $client->request(
            'GET',
            '/?'.http_build_query([
                'q' => $query,
                'sort' => 'price_asc',
            ])
        );

        self::assertResponseIsSuccessful();

        self::assertSame(
            [
                $products['alpha']->getName(),
                $products['beta']->getName(),
                $products['gamma']->getName(),
            ],
            $this->catalogProductNames(
                $crawler
            )
        );
    }

    public function testCatalogCanSortByDescendingPrice(): void
    {
        $client = static::createClient();

        [$query, $products] =
            $this->createCatalog();

        $crawler = $client->request(
            'GET',
            '/?'.http_build_query([
                'q' => $query,
                'sort' => 'price_desc',
            ])
        );

        self::assertResponseIsSuccessful();

        self::assertSame(
            [
                $products['gamma']->getName(),
                $products['beta']->getName(),
                $products['alpha']->getName(),
            ],
            $this->catalogProductNames(
                $crawler
            )
        );
    }

    public function testCatalogCanSortByName(): void
    {
        $client = static::createClient();

        [$query, $products] =
            $this->createCatalog();

        $crawler = $client->request(
            'GET',
            '/?'.http_build_query([
                'q' => $query,
                'sort' => 'name_asc',
            ])
        );

        self::assertResponseIsSuccessful();

        self::assertSame(
            [
                $products['alpha']->getName(),
                $products['beta']->getName(),
                $products['gamma']->getName(),
            ],
            $this->catalogProductNames(
                $crawler
            )
        );
    }

    public function testSortControlPreservesCatalogContext(): void
    {
        $client = static::createClient();

        [$query, $products, $category] =
            $this->createCatalog();

        $crawler = $client->request(
            'GET',
            '/?'.http_build_query([
                'q' => $query,
                'category' =>
                    $category->getSlug(),
                'sort' => 'price_desc',
            ])
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->filter(
                'form.catalog-controls'
            )
            ->form();

        $values = $form->getValues();

        self::assertSame(
            $query,
            $values['q']
        );

        self::assertSame(
            $category->getSlug(),
            $values['category']
        );

        self::assertSame(
            'price_desc',
            $values['sort']
        );

        self::assertSame(
            $products['gamma']->getName(),
            $this->catalogProductNames(
                $crawler
            )[0]
        );
    }

    /**
     * @return array{
     *     string,
     *     array{
     *         alpha: Product,
     *         beta: Product,
     *         gamma: Product
     *     },
     *     Category
     * }
     */
    private function createCatalog(): array
    {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $category = (new Category())
            ->setName(
                'Catalog Sort '.$suffix
            )
            ->setSlug(
                'catalog-sort-test-category-'
                .$suffix
            );

        $alpha = $this->product(
            'Alpha',
            $suffix,
            1000,
            $category
        );

        $beta = $this->product(
            'Beta',
            $suffix,
            2000,
            $category
        );

        $gamma = $this->product(
            'Gamma',
            $suffix,
            3000,
            $category
        );

        $this->em()->persist(
            $category
        );
        $this->em()->persist($alpha);
        $this->em()->persist($beta);
        $this->em()->persist($gamma);
        $this->em()->flush();

        return [
            $suffix,
            [
                'alpha' => $alpha,
                'beta' => $beta,
                'gamma' => $gamma,
            ],
            $category,
        ];
    }

    private function product(
        string $name,
        string $suffix,
        int $priceCents,
        Category $category
    ): Product {
        $slugName = strtolower($name);

        return (new Product())
            ->setName(
                sprintf(
                    'Catalog %s %s',
                    $suffix,
                    $name
                )
            )
            ->setSlug(
                sprintf(
                    'catalog-sort-test-%s-%s',
                    $suffix,
                    $slugName
                )
            )
            ->setDescription(
                'Produit utilisé pour tester '
                .'le tri du catalogue '.$suffix
            )
            ->setPriceCents(
                $priceCents
            )
            ->setStock(10)
            ->setCategory($category);
    }

    /**
     * @return list<string>
     */
    private function catalogProductNames(
        object $crawler
    ): array {
        return $crawler
            ->filter(
                '#catalogue '
                .'.js-product-card h3'
            )
            ->each(
                static fn ($node): string =>
                    trim($node->text())
            );
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(
            EntityManagerInterface::class
        );
    }
}
