<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CustomerProfileManagementTest extends WebTestCase
{
    private const EMAIL = 'profile-management@shopwho.local';
    private const OTHER_EMAIL = 'profile-existing@shopwho.local';

    private const PASSWORD = 'Profile-Test-2026!';
    private const NEW_PASSWORD = 'New-Profile-Test-2026!';

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
        $this->removeUser(self::OTHER_EMAIL);

        static::ensureKernelShutdown();
    }

    public function testAnonymousUserCannotEditProfile(): void
    {
        $client = static::createClient();

        $client->request('GET', '/profil/modifier');

        self::assertResponseRedirects('/connexion');
    }

    public function testCustomerCanEditProfileWithoutChangingPassword(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            self::EMAIL,
            self::PASSWORD,
            'Camille',
            'Client',
        );

        $client->loginUser($user);

        $crawler = $client->request('GET', '/profil/modifier');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Modifier mon profil');

        $client->submit(
            $crawler->selectButton('Enregistrer les modifications')->form([
                'profile[firstName]' => 'Charlie',
                'profile[lastName]' => 'Shopwho',
                'profile[email]' => 'new-profile@shopwho.local',
                'profile[newPassword][first]' => '',
                'profile[newPassword][second]' => '',
                'profile[currentPassword]' => self::PASSWORD,
            ])
        );

        self::assertResponseRedirects('/profil');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            'body',
            'new-profile@shopwho.local'
        );

        $user = $this->findUser('new-profile@shopwho.local');

        self::assertNotNull($user);
        self::assertSame('Charlie', $user->getFirstName());
        self::assertSame('Shopwho', $user->getLastName());

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );

        self::assertTrue(
            $hasher->isPasswordValid($user, self::PASSWORD)
        );

        $this->removeUser('new-profile@shopwho.local');
    }

    public function testCustomerCanChangePassword(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            self::EMAIL,
            self::PASSWORD,
            'Camille',
            'Client',
        );

        $client->loginUser($user);

        $crawler = $client->request('GET', '/profil/modifier');

        $client->submit(
            $crawler->selectButton('Enregistrer les modifications')->form([
                'profile[firstName]' => 'Camille',
                'profile[lastName]' => 'Client',
                'profile[email]' => self::EMAIL,
                'profile[newPassword][first]' => self::NEW_PASSWORD,
                'profile[newPassword][second]' => self::NEW_PASSWORD,
                'profile[currentPassword]' => self::PASSWORD,
            ])
        );

        self::assertResponseRedirects('/profil');

        $user = $this->findUser(self::EMAIL);

        self::assertNotNull($user);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );

        self::assertTrue(
            $hasher->isPasswordValid($user, self::NEW_PASSWORD)
        );

        self::assertFalse(
            $hasher->isPasswordValid($user, self::PASSWORD)
        );
    }

    public function testWrongCurrentPasswordPreventsAllChanges(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            self::EMAIL,
            self::PASSWORD,
            'Camille',
            'Client',
        );

        $client->loginUser($user);

        $crawler = $client->request('GET', '/profil/modifier');

        $client->submit(
            $crawler->selectButton('Enregistrer les modifications')->form([
                'profile[firstName]' => 'Modified',
                'profile[lastName]' => 'Name',
                'profile[email]' => 'wrong-password-change@shopwho.local',
                'profile[newPassword][first]' => self::NEW_PASSWORD,
                'profile[newPassword][second]' => self::NEW_PASSWORD,
                'profile[currentPassword]' => 'Wrong-Password-2026!',
            ])
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'body',
            'Le mot de passe actuel est incorrect.'
        );

        self::assertNull(
            $this->findUser('wrong-password-change@shopwho.local')
        );

        $user = $this->findUser(self::EMAIL);

        self::assertNotNull($user);
        self::assertSame('Camille', $user->getFirstName());
        self::assertSame('Client', $user->getLastName());

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );

        self::assertTrue(
            $hasher->isPasswordValid($user, self::PASSWORD)
        );
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            self::EMAIL,
            self::PASSWORD,
            'Camille',
            'Client',
        );

        $this->createUser(
            self::OTHER_EMAIL,
            'Other-Profile-2026!',
            'Other',
            'Customer',
        );

        $client->loginUser($user);

        $crawler = $client->request('GET', '/profil/modifier');

        $client->submit(
            $crawler->selectButton('Enregistrer les modifications')->form([
                'profile[firstName]' => 'Camille',
                'profile[lastName]' => 'Client',
                'profile[email]' => self::OTHER_EMAIL,
                'profile[newPassword][first]' => '',
                'profile[newPassword][second]' => '',
                'profile[currentPassword]' => self::PASSWORD,
            ])
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'body',
            'Cette adresse e-mail est déjà utilisée.'
        );

        $originalUser = $this->findUser(self::EMAIL);

        self::assertNotNull($originalUser);
        self::assertSame(self::EMAIL, $originalUser->getEmail());

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        self::assertCount(
            1,
            $em->getRepository(User::class)->findBy([
                'email' => self::OTHER_EMAIL,
            ])
        );
    }

    public function testNewPasswordConfirmationMustMatch(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            self::EMAIL,
            self::PASSWORD,
            'Camille',
            'Client',
        );

        $client->loginUser($user);

        $crawler = $client->request('GET', '/profil/modifier');

        $client->submit(
            $crawler->selectButton('Enregistrer les modifications')->form([
                'profile[firstName]' => 'Camille',
                'profile[lastName]' => 'Client',
                'profile[email]' => self::EMAIL,
                'profile[newPassword][first]' => self::NEW_PASSWORD,
                'profile[newPassword][second]' => 'Different-Password-2026!',
                'profile[currentPassword]' => self::PASSWORD,
            ])
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'body',
            'Les nouveaux mots de passe ne correspondent pas.'
        );

        $user = $this->findUser(self::EMAIL);

        self::assertNotNull($user);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );

        self::assertTrue(
            $hasher->isPasswordValid($user, self::PASSWORD)
        );

        self::assertFalse(
            $hasher->isPasswordValid($user, self::NEW_PASSWORD)
        );
    }

    private function createUser(
        string $email,
        string $password,
        string $firstName,
        string $lastName,
    ): User {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName);

        $user->setPassword(
            $hasher->hashPassword($user, $password)
        );

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function findUser(string $email): ?User
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $em->clear();

        return $em->getRepository(User::class)->findOneBy([
            'email' => $email,
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
