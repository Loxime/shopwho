<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Kernel;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class CategoryRepositoryTest extends KernelTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testFindForNavigationFiltersOrdersAndLimitsCategories(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var CategoryRepository $categories */
        $categories = $em->getRepository(Category::class);

        $fixtures = [
            $this->createCategory('Navigation cachée', 'navigation-cachee-test', -20000, false),
            $this->createCategory('Navigation bravo', 'navigation-bravo-test', -10000),
            $this->createCategory('Navigation alpha', 'navigation-alpha-test', -10000),
            $this->createCategory('Navigation charlie', 'navigation-charlie-test', -9999),
            $this->createCategory('Navigation delta', 'navigation-delta-test', -9998),
            $this->createCategory('Navigation echo', 'navigation-echo-test', -9997),
            $this->createCategory('Navigation foxtrot', 'navigation-foxtrot-test', -9996),
            $this->createCategory('Navigation golf', 'navigation-golf-test', -9995),
        ];

        foreach ($fixtures as $category) {
            $em->persist($category);
        }
        $em->flush();

        $results = $categories->findForNavigation();

        self::assertCount(6, $results);
        self::assertSame([
            'Navigation alpha',
            'Navigation bravo',
            'Navigation charlie',
            'Navigation delta',
            'Navigation echo',
            'Navigation foxtrot',
        ], array_map(static fn (Category $category): string => $category->getName(), $results));
        self::assertNotContains('Navigation cachée', array_map(static fn (Category $category): string => $category->getName(), $results));

        foreach ($fixtures as $category) {
            $em->remove($category);
        }
        $em->flush();
    }

    private function createCategory(string $name, string $slug, int $position, bool $visible = true): Category
    {
        return (new Category())
            ->setName($name)
            ->setSlug($slug)
            ->setNavigationPosition($position)
            ->setShowInNavigation($visible);
    }
}
