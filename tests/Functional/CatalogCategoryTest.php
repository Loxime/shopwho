<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class CatalogCategoryTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testCatalogFiltersProductsByCategorySlug(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $firstCategory = (new Category())->setName('Filtre alpha')->setSlug('filtre-alpha-test');
        $secondCategory = (new Category())->setName('Filtre bêta')->setSlug('filtre-beta-test');
        $firstProduct = $this->createProduct('Produit filtre alpha', 'produit-filtre-alpha-test', $firstCategory);
        $secondProduct = $this->createProduct('Produit filtre bêta', 'produit-filtre-beta-test', $secondCategory);

        $em->persist($firstCategory);
        $em->persist($secondCategory);
        $em->persist($firstProduct);
        $em->persist($secondProduct);
        $em->flush();

        $client->request('GET', '/?category=filtre-alpha-test');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#catalogue .grid', 'Produit filtre alpha');
        self::assertSelectorTextNotContains('#catalogue .grid', 'Produit filtre bêta');

        $em->remove($firstProduct);
        $em->remove($secondProduct);
        $em->remove($firstCategory);
        $em->remove($secondCategory);
        $em->flush();
    }

    private function createProduct(string $name, string $slug, Category $category): Product
    {
        return (new Product())
            ->setName($name)
            ->setSlug($slug)
            ->setDescription('Produit synthétique utilisé uniquement par le test du catalogue.')
            ->setPriceCents(1000)
            ->setStock(1)
            ->setCategory($category);
    }
}
