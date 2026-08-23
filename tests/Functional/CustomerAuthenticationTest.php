<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CustomerAuthenticationTest extends WebTestCase
{
    private const EMAIL = 'customer-test@shopwho.local';
    private const PASSWORD = 'Customer-Test-2026!';

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testAnonymousUserIsRedirectedFromProfileToCustomerLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profil');

        self::assertResponseRedirects('/connexion');
    }

    public function testCustomerCanLoginAndSeeProfile(): void
    {
        $client = static::createClient();
        $this->createUser(['ROLE_USER']);

        $crawler = $client->request('GET', '/connexion');
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => self::EMAIL,
            '_password' => self::PASSWORD,
        ]));

        self::assertResponseRedirects('/profil');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon profil');
        self::assertSelectorTextContains('.profile-details', self::EMAIL);
    }

    public function testInvalidCustomerPasswordIsRejectedCleanly(): void
    {
        $client = static::createClient();
        $this->createUser(['ROLE_USER']);

        $crawler = $client->request('GET', '/connexion');
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => self::EMAIL,
            '_password' => 'incorrect',
        ]));

        self::assertResponseRedirects('/connexion');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.login-error', 'Identifiants incorrects');
    }

    public function testCustomerCannotAccessAdminRoutes(): void
    {
        $client = static::createClient();
        $user = $this->createUser(['ROLE_USER']);
        $client->loginUser($user);

        $client->request('GET', '/admin/products');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminStillHasCustomerAndBackofficeAccess(): void
    {
        $client = static::createClient();
        $admin = $this->createUser(['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Se rendre sur le backoffice');

        $client->request('GET', '/admin/products');
        self::assertResponseIsSuccessful();
    }

    private function createUser(array $roles): User
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $existing = $em->getRepository(User::class)->findOneBy(['email' => self::EMAIL]);
        if ($existing) {
            $em->remove($existing);
            $em->flush();
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = (new User())->setEmail(self::EMAIL)->setFirstName('Camille')->setLastName('Client')->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
