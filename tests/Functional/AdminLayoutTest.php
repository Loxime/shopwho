<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class AdminLayoutTest extends WebTestCase
{
    private const ADMIN_PATHS = [
        '/admin/products',
        '/admin/categories',
        '/admin/data-reset',
    ];

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testAnonymousCannotAccessAdministrationSections(): void
    {
        $client = static::createClient();

        foreach (self::ADMIN_PATHS as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('/connexion');
        }
    }

    public function testRegularUserCannotAccessAdministrationSections(): void
    {
        $client = static::createClient();
        $user = $this->createUser('layout-user', ['ROLE_USER']);
        $client->loginUser($user);

        foreach (self::ADMIN_PATHS as $path) {
            $client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
        }

        $this->removeUser($user);
    }

    public function testAdminPagesUseDedicatedShellAndNavigation(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('layout-admin', ['ROLE_ADMIN']);
        $client->loginUser($admin);

        foreach (self::ADMIN_PATHS as $path) {
            $client->request('GET', $path);

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('[data-admin-shell]');
            self::assertSelectorExists('link[href="/styles/admin.css"]');
            self::assertSelectorExists('.admin-sidebar-brand');
            self::assertSelectorExists('.admin-nav a[href="/admin/products"]');
            self::assertSelectorExists('.admin-nav a[href="/admin/categories"]');
            self::assertSelectorExists('.admin-nav a[href="/admin/data-reset"]');
            self::assertSelectorTextContains('.admin-sidebar-footer', 'Retour à la boutique');
            self::assertSelectorExists('.admin-sidebar-footer a[href="/"]');
            self::assertSelectorNotExists('.site-header');
            self::assertSelectorNotExists('.category-bar');
            self::assertSelectorNotExists('.footer');
            self::assertSelectorTextNotContains('body', 'Livraison offerte');
            self::assertSelectorTextNotContains('body', 'Mesure d’audience Shopwho');
        }

        $this->removeUser($admin);
    }

    /** @param list<string> $roles */
    private function createUser(string $prefix, array $roles): User
    {
        $user = (new User())
            ->setEmail($prefix.'-'.bin2hex(random_bytes(6)).'@shopwho.test')
            ->setPassword('unused')
            ->setRoles($roles);

        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    private function removeUser(User $user): void
    {
        $entityManager = $this->entityManager();
        $managedUser = $entityManager->find(User::class, $user->getId());
        if ($managedUser) {
            $entityManager->remove($managedUser);
            $entityManager->flush();
        }
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
