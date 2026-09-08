<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class ProfileDashboardTest extends WebTestCase
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
                        'profile-dashboard-%@shopwho.test'
                "
            );

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testAnonymousUserCannotAccessProfileDashboard(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/profil'
        );

        self::assertResponseRedirects(
            '/connexion'
        );
    }

    public function testCustomerProfileDisplaysDashboardNavigation(): void
    {
        $client = static::createClient();

        $user = $this->createUser();

        $client->loginUser(
            $user
        );

        $crawler = $client->request(
            'GET',
            '/profil'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '.profile-dashboard'
        );

        self::assertSelectorTextContains(
            '.profile-dashboard-hero',
            'Maxime Falchero'
        );

        self::assertSelectorTextContains(
            '.profile-dashboard-hero',
            $user->getEmail()
        );

        self::assertSelectorCount(
            6,
            '.profile-dashboard-nav a'
        );

        self::assertSelectorExists(
            '.profile-dashboard-nav '
            .'a.is-active[href="/profil"]'
        );

        self::assertSelectorExists(
            '.profile-dashboard-nav '
            .'a[href="/profil/commandes"]'
        );

        self::assertSelectorExists(
            '.profile-dashboard-nav '
            .'a[href="/profil/favoris"]'
        );

        self::assertSelectorExists(
            '.profile-dashboard-nav '
            .'a[href="/profil/adresses"]'
        );

        self::assertSame(
            '/profil/modifier',
            $crawler
                ->filter(
                    '.profile-dashboard-edit'
                )
                ->attr('href')
        );
    }

    public function testProfileDisplaysAccountInformationAndEmptyOrders(): void
    {
        $client = static::createClient();

        $user = $this->createUser();

        $client->loginUser(
            $user
        );

        $client->request(
            'GET',
            '/profil'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.profile-account-details',
            'Maxime'
        );

        self::assertSelectorTextContains(
            '.profile-account-details',
            'Falchero'
        );

        self::assertSelectorTextContains(
            '.profile-orders-empty',
            'Aucune commande enregistrée'
        );

        self::assertSelectorExists(
            'a[href="/profil/supprimer"]'
        );
    }

    private function createUser(): User
    {
        $user = (new User())
            ->setEmail(
                'profile-dashboard-'
                .bin2hex(random_bytes(6))
                .'@shopwho.test'
            )
            ->setFirstName('Maxime')
            ->setLastName('Falchero')
            ->setPassword('unused');

        static::getContainer()
            ->get(EntityManagerInterface::class)
            ->persist($user);

        static::getContainer()
            ->get(EntityManagerInterface::class)
            ->flush();

        return $user;
    }
}
