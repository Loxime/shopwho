<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\DataOrigin;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CustomerRegistrationTest extends WebTestCase
{
    private const EMAIL = 'registration-test@shopwho.local';
    private const PASSWORD = 'Registration-Test-2026!';

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        static::bootKernel();
        $this->removeUser(self::EMAIL);
        static::ensureKernelShutdown();
    }

    public function testRegistrationPageIsPubliclyAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/inscription');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer un compte');
        self::assertSelectorExists('form');
    }

    public function testCustomerCanRegister(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/inscription');

        $client->submit(
            $crawler->selectButton('Créer mon compte')->form([
                'registration[firstName]' => 'Camille',
                'registration[lastName]' => 'Client',
                'registration[email]' => self::EMAIL,
                'registration[plainPassword][first]' => self::PASSWORD,
                'registration[plainPassword][second]' => self::PASSWORD,
            ])
        );

        self::assertResponseRedirects('/connexion');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '.flash',
            'Votre compte a été créé'
        );

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->findOneBy([
            'email' => self::EMAIL,
        ]);

        self::assertNotNull($user);
        self::assertSame('Camille', $user->getFirstName());
        self::assertSame('Client', $user->getLastName());
        self::assertSame(self::EMAIL, $user->getEmail());
        self::assertSame(DataOrigin::Native, $user->getDataOrigin());

        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertNotContains('ROLE_ADMIN', $user->getRoles());

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );

        self::assertTrue(
            $hasher->isPasswordValid($user, self::PASSWORD)
        );

        self::assertNotSame(
            self::PASSWORD,
            $user->getPassword()
        );
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $client = static::createClient();

        $this->createExistingUser();

        $crawler = $client->request('GET', '/inscription');

        $client->submit(
            $crawler->selectButton('Créer mon compte')->form([
                'registration[firstName]' => 'Autre',
                'registration[lastName]' => 'Client',
                'registration[email]' => self::EMAIL,
                'registration[plainPassword][first]' => self::PASSWORD,
                'registration[plainPassword][second]' => self::PASSWORD,
            ])
        );
    
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            'body',
            'Un compte existe déjà avec cette adresse e-mail.'
        );

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $users = $em->getRepository(User::class)->findBy([
            'email' => self::EMAIL,
        ]);

        self::assertCount(1, $users);
    }

    public function testPasswordConfirmationMustMatch(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/inscription');

        $client->submit(
            $crawler->selectButton('Créer mon compte')->form([
                'registration[firstName]' => 'Camille',
                'registration[lastName]' => 'Client',
                'registration[email]' => self::EMAIL,
                'registration[plainPassword][first]' => self::PASSWORD,
                'registration[plainPassword][second]' => 'Different-Password-2026!',
            ])
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            'body',
            'Les mots de passe ne correspondent pas.'
        );

        self::assertNull($this->findUser());
    }

    public function testTooShortPasswordIsRejected(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/inscription');

        $client->submit(
            $crawler->selectButton('Créer mon compte')->form([
                'registration[firstName]' => 'Camille',
                'registration[lastName]' => 'Client',
                'registration[email]' => self::EMAIL,
                'registration[plainPassword][first]' => 'court',
                'registration[plainPassword][second]' => 'court',
            ])
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains(
            'body',
            'Le mot de passe doit contenir au moins 12 caractères.'
        );

        self::assertNull($this->findUser());
    }

    public function testAuthenticatedCustomerCannotRegisterAgain(): void
    {
        $client = static::createClient();

        $user = $this->createExistingUser();

        $client->loginUser($user);
        $client->request('GET', '/inscription');

        self::assertResponseRedirects('/profil');
    }

    private function createExistingUser(): User
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );

        $user = (new User())
            ->setEmail(self::EMAIL)
            ->setFirstName('Existing')
            ->setLastName('Customer');

        $user->setPassword(
            $hasher->hashPassword($user, self::PASSWORD)
        );

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function findUser(): ?User
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return $em->getRepository(User::class)->findOneBy([
            'email' => self::EMAIL,
        ]);
    }

    private function removeUser(string $email): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $em->getRepository(User::class)->findOneBy([
            'email' => $email,
        ]);

        if ($user) {
            $em->remove($user);
            $em->flush();
        }
    }
}
