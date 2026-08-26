<?php

namespace App\Tests\Functional;

use App\Entity\Address;
use App\Entity\User;
use App\Enum\AddressType;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CustomerAddressManagementTest extends WebTestCase
{
    private const PASSWORD = 'Address-Test-2026!';

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
        $this->cleanupFixtures();
        static::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        if (static::$booted) {
            $this->cleanupFixtures();
        } else {
            static::bootKernel();
            $this->cleanupFixtures();
            static::ensureKernelShutdown();
        }

        parent::tearDown();
    }

    public function testAnonymousUserCannotAccessAddresses(): void
    {
        $client = static::createClient();

        $client->request('GET', '/profil/adresses');

        self::assertResponseRedirects('/connexion');
    }

    public function testAddressPagePrefillsCustomerIdentity(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            'address-test-prefill@shopwho.local',
            'Maxime',
            'Test'
        );

        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/profil/adresses'
        );

        self::assertResponseIsSuccessful();

        self::assertSame(
            'Maxime',
            $crawler
                ->filter(
                    'input[name="shipping_address[firstName]"]'
                )
                ->attr('value')
        );

        self::assertSame(
            'Test',
            $crawler
                ->filter(
                    'input[name="shipping_address[lastName]"]'
                )
                ->attr('value')
        );

        self::assertSame(
            'Maxime',
            $crawler
                ->filter(
                    'input[name="billing_address[firstName]"]'
                )
                ->attr('value')
        );

        self::assertSame(
            'Test',
            $crawler
                ->filter(
                    'input[name="billing_address[lastName]"]'
                )
                ->attr('value')
        );
    }

    public function testCustomerCanCreateShippingAddress(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            'address-test-shipping@shopwho.local',
            'Maxime',
            'Falchero'
        );

        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/profil/adresses'
        );

        $form = $crawler
            ->filter('form[name="shipping_address"]')
            ->form();

        $client->submit($form, [
            'shipping_address[firstName]' => 'Maxime',
            'shipping_address[lastName]' => 'Falchero',
            'shipping_address[line1]' => '10 rue des Tests',
            'shipping_address[line2]' => 'Appartement 42',
            'shipping_address[postalCode]' => '63000',
            'shipping_address[city]' => 'Clermont-Ferrand',
            'shipping_address[countryCode]' => 'FR',
        ]);

        self::assertResponseRedirects('/profil/adresses');

        $address = $this->findAddress(
            $user->getId(),
            AddressType::Shipping
        );

        self::assertNotNull($address);

        self::assertSame(
            'Maxime',
            $address['first_name']
        );

        self::assertSame(
            'Falchero',
            $address['last_name']
        );

        self::assertSame(
            '10 rue des Tests',
            $address['line1']
        );

        self::assertSame(
            'Appartement 42',
            $address['line2']
        );

        self::assertSame(
            '63000',
            $address['postal_code']
        );

        self::assertSame(
            'Clermont-Ferrand',
            $address['city']
        );

        self::assertSame(
            'FR',
            $address['country_code']
        );
    }

    public function testUpdatingShippingAddressDoesNotCreateDuplicate(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            'address-test-update@shopwho.local',
            'Maxime',
            'Falchero'
        );

        $this->createAddress(
            $user,
            AddressType::Shipping,
            '12 avenue Initiale',
            '63000',
            'Clermont-Ferrand',
            'FR'
        );

        $userId = $user->getId();

        $initialAddress = $this->findAddress(
            $userId,
            AddressType::Shipping
        );

        self::assertNotNull($initialAddress);

        $initialId = (int) $initialAddress['id'];

        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/profil/adresses'
        );

        $form = $crawler
            ->filter('form[name="shipping_address"]')
            ->form();

        $client->submit($form, [
            'shipping_address[firstName]' => 'Maxime',
            'shipping_address[lastName]' => 'Falchero',
            'shipping_address[line1]' => '99 boulevard Modifié',
            'shipping_address[line2]' => '',
            'shipping_address[postalCode]' => '69001',
            'shipping_address[city]' => 'Lyon',
            'shipping_address[countryCode]' => 'FR',
        ]);

        self::assertResponseRedirects('/profil/adresses');

        self::assertSame(
            1,
            $this->countAddresses(
                $userId,
                AddressType::Shipping
            )
        );

        $updatedAddress = $this->findAddress(
            $userId,
            AddressType::Shipping
        );

        self::assertNotNull($updatedAddress);

        self::assertSame(
            $initialId,
            (int) $updatedAddress['id']
        );

        self::assertSame(
            '99 boulevard Modifié',
            $updatedAddress['line1']
        );

        self::assertNull(
            $updatedAddress['line2']
        );

        self::assertSame(
            '69001',
            $updatedAddress['postal_code']
        );

        self::assertSame(
            'Lyon',
            $updatedAddress['city']
        );
    }

    public function testShippingAndBillingAddressesAreIndependent(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            'address-test-independent@shopwho.local',
            'Maxime',
            'Falchero'
        );

        $client->loginUser($user);

        /*
         * Adresse de livraison.
         */
        $crawler = $client->request(
            'GET',
            '/profil/adresses'
        );

        $shippingForm = $crawler
            ->filter('form[name="shipping_address"]')
            ->form();

        $client->submit($shippingForm, [
            'shipping_address[firstName]' => 'Maxime',
            'shipping_address[lastName]' => 'Falchero',
            'shipping_address[line1]' => '1 rue Livraison',
            'shipping_address[line2]' => '',
            'shipping_address[postalCode]' => '63000',
            'shipping_address[city]' => 'Clermont-Ferrand',
            'shipping_address[countryCode]' => 'FR',
        ]);

        self::assertResponseRedirects('/profil/adresses');

        /*
         * Adresse de facturation.
         */
        $crawler = $client->followRedirect();

        $billingForm = $crawler
            ->filter('form[name="billing_address"]')
            ->form();

        $client->submit($billingForm, [
            'billing_address[firstName]' => 'Maxime',
            'billing_address[lastName]' => 'Falchero',
            'billing_address[line1]' => '2 rue Facturation',
            'billing_address[line2]' => 'Bâtiment B',
            'billing_address[postalCode]' => '75001',
            'billing_address[city]' => 'Paris',
            'billing_address[countryCode]' => 'FR',
        ]);

        self::assertResponseRedirects('/profil/adresses');

        $userId = $user->getId();

        self::assertSame(
            1,
            $this->countAddresses(
                $userId,
                AddressType::Shipping
            )
        );

        self::assertSame(
            1,
            $this->countAddresses(
                $userId,
                AddressType::Billing
            )
        );

        self::assertSame(
            2,
            $this->countAllAddresses($userId)
        );

        $shipping = $this->findAddress(
            $userId,
            AddressType::Shipping
        );

        $billing = $this->findAddress(
            $userId,
            AddressType::Billing
        );

        self::assertNotNull($shipping);
        self::assertNotNull($billing);

        self::assertSame(
            '1 rue Livraison',
            $shipping['line1']
        );

        self::assertSame(
            'Clermont-Ferrand',
            $shipping['city']
        );

        self::assertSame(
            '2 rue Facturation',
            $billing['line1']
        );

        self::assertSame(
            'Paris',
            $billing['city']
        );
    }

    public function testInvalidAddressIsRejected(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            'address-test-invalid@shopwho.local',
            'Maxime',
            'Falchero'
        );

        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/profil/adresses'
        );

        $form = $crawler
            ->filter('form[name="shipping_address"]')
            ->form();

        $client->submit($form, [
            'shipping_address[firstName]' => '',
            'shipping_address[lastName]' => '',
            'shipping_address[line1]' => '',
            'shipping_address[line2]' => '',
            'shipping_address[postalCode]' => '',
            'shipping_address[city]' => '',
            'shipping_address[countryCode]' => 'FR',
        ]);

        self::assertResponseStatusCodeSame(422);

        self::assertSame(
            0,
            $this->countAddresses(
                $user->getId(),
                AddressType::Shipping
            )
        );
    }

    public function testDeletingAccountDeletesCustomerAddresses(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            'address-test-deletion@shopwho.local',
            'Maxime',
            'Falchero'
        );

        $userId = $user->getId();

        $this->createAddress(
            $user,
            AddressType::Shipping,
            '1 rue Livraison',
            '63000',
            'Clermont-Ferrand',
            'FR'
        );

        $this->createAddress(
            $user,
            AddressType::Billing,
            '2 rue Facturation',
            '75001',
            'Paris',
            'FR'
        );

        self::assertSame(
            2,
            $this->countAllAddresses($userId)
        );

        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/profil/supprimer'
        );

        $form = $crawler
            ->filter('form[name="delete_account"]')
            ->form();

        $client->submit($form, [
            'delete_account[currentPassword]' => self::PASSWORD,
            'delete_account[confirmation]' => 'SUPPRIMER',
        ]);

        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('/');

        self::assertSame(
            0,
            $this->countAllAddresses($userId)
        );
    }

    private function createUser(
        string $email,
        string $firstName,
        string $lastName
    ): User {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName);

        $user->setPassword(
            $hasher->hashPassword(
                $user,
                self::PASSWORD
            )
        );

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createAddress(
        User $user,
        AddressType $type,
        string $line1,
        string $postalCode,
        string $city,
        string $countryCode
    ): Address {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $address = (new Address($user, $type))
            ->setFirstName(
                $user->getFirstName() ?? ''
            )
            ->setLastName(
                $user->getLastName() ?? ''
            )
            ->setLine1($line1)
            ->setPostalCode($postalCode)
            ->setCity($city)
            ->setCountryCode($countryCode);

        $em->persist($address);
        $em->flush();

        return $address;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAddress(
        ?int $userId,
        AddressType $type
    ): ?array {
        if ($userId === null) {
            return null;
        }

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $result = $em
            ->getConnection()
            ->fetchAssociative(
                '
                    SELECT *
                    FROM customer_address
                    WHERE user_id = :userId
                      AND type = :type
                ',
                [
                    'userId' => $userId,
                    'type' => $type->value,
                ]
            );

        return $result === false
            ? null
            : $result;
    }

    private function countAddresses(
        ?int $userId,
        AddressType $type
    ): int {
        if ($userId === null) {
            return 0;
        }

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        return (int) $em
            ->getConnection()
            ->fetchOne(
                '
                    SELECT COUNT(*)
                    FROM customer_address
                    WHERE user_id = :userId
                      AND type = :type
                ',
                [
                    'userId' => $userId,
                    'type' => $type->value,
                ]
            );
    }

    private function countAllAddresses(
        ?int $userId
    ): int {
        if ($userId === null) {
            return 0;
        }

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        return (int) $em
            ->getConnection()
            ->fetchOne(
                '
                    SELECT COUNT(*)
                    FROM customer_address
                    WHERE user_id = :userId
                ',
                [
                    'userId' => $userId,
                ]
            );
    }

    private function cleanupFixtures(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        /*
         * La FK customer_address.user_id est ON DELETE CASCADE.
         * Supprimer les comptes suffit donc à nettoyer les adresses.
         */
        $em
            ->getConnection()
            ->executeStatement(
                "
                    DELETE FROM app_user
                    WHERE email LIKE 'address-test-%@shopwho.local'
                "
            );

        $em->clear();
    }
}
