<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class AdminPermissionsTest extends WebTestCase
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

        static::getContainer()
            ->get(EntityManagerInterface::class)
            ->getConnection()
            ->executeStatement(
                "
                    DELETE FROM app_user
                    WHERE email LIKE
                        'permission-%@shopwho.test'
                "
            );

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testCatalogManagerOnlySeesCatalogAdministration(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createUser(
                'catalog',
                ['ROLE_CATALOG_MANAGER']
            )
        );

        $client->request(
            'GET',
            '/admin'
        );

        self::assertResponseRedirects(
            '/admin/products'
        );

        $crawler = $client->request(
            'GET',
            '/admin/products'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '.admin-nav '
            .'a[href="/admin/products"]'
        );

        self::assertSelectorExists(
            '.admin-nav '
            .'a[href="/admin/categories"]'
        );

        self::assertSelectorNotExists(
            '.admin-nav '
            .'a[href="/admin/offers"]'
        );

        self::assertSelectorNotExists(
            '.admin-nav '
            .'a[href="/admin/analytics"]'
        );

        $client->request(
            'GET',
            '/admin/offers'
        );

        self::assertResponseStatusCodeSame(
            403
        );
    }

    public function testMarketingManagerOnlyAccessesMarketingTools(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createUser(
                'marketing',
                ['ROLE_MARKETING_MANAGER']
            )
        );

        $client->request(
            'GET',
            '/admin'
        );

        self::assertResponseRedirects(
            '/admin/offers'
        );

        $client->request(
            'GET',
            '/admin/offers'
        );

        self::assertResponseIsSuccessful();

        $client->request(
            'GET',
            '/admin/reviews'
        );

        self::assertResponseIsSuccessful();

        $client->request(
            'GET',
            '/admin/products'
        );

        self::assertResponseStatusCodeSame(
            403
        );
    }

    public function testDataAnalystOnlyAccessesAnalytics(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createUser(
                'analytics',
                ['ROLE_DATA_ANALYST']
            )
        );

        $client->request(
            'GET',
            '/admin'
        );

        self::assertResponseRedirects(
            '/admin/analytics'
        );

        $client->request(
            'GET',
            '/admin/analytics'
        );

        self::assertResponseIsSuccessful();

        $client->request(
            'GET',
            '/admin/data-import'
        );

        self::assertResponseStatusCodeSame(
            403
        );
    }

    public function testDataManagerAccessesImportAndReset(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createUser(
                'data',
                ['ROLE_DATA_MANAGER']
            )
        );

        $client->request(
            'GET',
            '/admin'
        );

        self::assertResponseRedirects(
            '/admin/data-import'
        );

        $client->request(
            'GET',
            '/admin/data-import'
        );

        self::assertResponseIsSuccessful();

        $client->request(
            'GET',
            '/admin/data-reset'
        );

        self::assertResponseIsSuccessful();

        $client->request(
            'GET',
            '/admin/products'
        );

        self::assertResponseStatusCodeSame(
            403
        );
    }

    public function testAdministratorKeepsFullBackofficeAccess(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createUser(
                'admin',
                ['ROLE_ADMIN']
            )
        );

        foreach (
            [
                '/admin/products',
                '/admin/categories',
                '/admin/offers',
                '/admin/reviews',
                '/admin/analytics',
                '/admin/data-import',
                '/admin/data-reset',
            ] as $path
        ) {
            $client->request(
                'GET',
                $path
            );

            self::assertResponseIsSuccessful();
        }
    }

    public function testCustomerCannotEnterBackoffice(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createUser(
                'customer',
                ['ROLE_USER']
            )
        );

        $client->request(
            'GET',
            '/admin'
        );

        self::assertResponseStatusCodeSame(
            403
        );

        $client->request(
            'GET',
            '/admin/products'
        );

        self::assertResponseStatusCodeSame(
            403
        );
    }

    private function createUser(
        string $suffix,
        array $roles
    ): User {
        $user = (new User())
            ->setEmail(
                'permission-'
                .$suffix
                .'-'
                .bin2hex(random_bytes(4))
                .'@shopwho.test'
            )
            ->setFirstName('Permission')
            ->setLastName('Test')
            ->setPassword('unused')
            ->setRoles($roles);

        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
