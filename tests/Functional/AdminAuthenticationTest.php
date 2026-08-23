<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminAuthenticationTest extends WebTestCase
{
    private const EMAIL = 'admin-test@shopwho.local';
    private const PASSWORD = 'Shopwho-Test-2026!';

    protected static function createKernel(array $options = []): KernelInterface
    {
    	return new Kernel(
        	$options['environment'] ?? 'test',
        	$options['debug'] ?? true,
    	);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        self::ensureKernelShutdown();
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/products');

        self::assertResponseRedirects('/admin/login');
    }

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Administration');
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
    }

    public function testInvalidCredentialsAreRejected(): void
    {
        $client = static::createClient();

        $this->createAdmin();

        $crawler = $client->request('GET', '/admin/login');

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => self::EMAIL,
            '_password' => 'mauvais-mot-de-passe',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/admin/login');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '.login-error',
            'Identifiants incorrects'
        );
    }

    public function testAdminCanLoginAndLogout(): void
    {
        $client = static::createClient();

        $this->createAdmin();

        $crawler = $client->request('GET', '/admin/login');

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => self::EMAIL,
            '_password' => self::PASSWORD,
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/admin/products');

        $client->followRedirect();

        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/logout');

        self::assertResponseRedirects('/');

        $client->followRedirect();

        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/products');

        self::assertResponseRedirects('/admin/login');
    }

    private function createAdmin(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $existingUser = $em
            ->getRepository(User::class)
            ->findOneBy(['email' => self::EMAIL]);

        if ($existingUser) {
            $em->remove($existingUser);
            $em->flush();
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );

        $user = (new User())
            ->setEmail(self::EMAIL)
            ->setRoles(['ROLE_ADMIN']);

        $user->setPassword(
            $hasher->hashPassword($user, self::PASSWORD)
        );

        $em->persist($user);
        $em->flush();
    }
}
