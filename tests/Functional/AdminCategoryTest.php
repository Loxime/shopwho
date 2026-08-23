<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class AdminCategoryTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testAnonymousUserIsRedirectedToAdminLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/categories');

        self::assertResponseRedirects('/connexion');
    }

    public function testAdminCannotDeleteCategoryContainingAProduct(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = (new User())
            ->setEmail('category-admin-test@shopwho.local')
            ->setPassword('not-used')
            ->setRoles(['ROLE_ADMIN']);
        $category = (new Category())
            ->setName('Catégorie protégée test')
            ->setSlug('categorie-protegee-test');
        $product = (new Product())
            ->setName('Produit protégé test')
            ->setSlug('produit-protege-test')
            ->setDescription('Produit synthétique utilisé uniquement par le test.')
            ->setPriceCents(1000)
            ->setStock(1)
            ->setCategory($category);

        $em->persist($admin);
        $em->persist($category);
        $em->persist($product);
        $em->flush();

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/categories');
        $form = $crawler->filter(sprintf('form[action="/admin/categories/%d"]', $category->getId()))->form();
        $client->submit($form);

        self::assertResponseRedirects('/admin/categories');
        $client->followRedirect();
        self::assertSelectorTextContains('.admin-flash-error', 'Réaffectez-les avant de la supprimer');

        $em->clear();
        self::assertNotNull($em->getRepository(Category::class)->findOneBy(['slug' => 'categorie-protegee-test']));

        $product = $em->getRepository(Product::class)->findOneBy(['slug' => 'produit-protege-test']);
        $category = $em->getRepository(Category::class)->findOneBy(['slug' => 'categorie-protegee-test']);
        $admin = $em->getRepository(User::class)->findOneBy(['email' => 'category-admin-test@shopwho.local']);
        if ($product) { $em->remove($product); }
        if ($category) { $em->remove($category); }
        if ($admin) { $em->remove($admin); }
        $em->flush();
    }
}
