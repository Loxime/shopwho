<?php

namespace App\Tests\Functional;

use App\Address\FrenchAddressLookup;
use App\Entity\Address;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\AddressType;
use App\Enum\NotificationType;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
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
        $this->installFrenchAddressLookupMock();

        $client->request('GET', '/profil/adresses');

        self::assertResponseRedirects('/connexion');
    }

    public function testAddressPagePrefillsCustomerIdentity(): void
    {
        $client = static::createClient();
        $this->installFrenchAddressLookupMock();

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
        $this->installFrenchAddressLookupMock();

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

        self::assertSame(
            1,
            $this->countNotifications(
                $user->getId()
            )
        );

        $notification =
            $this->findLatestNotification(
                $user->getId()
            );

        self::assertInstanceOf(
            Notification::class,
            $notification
        );

        self::assertSame(
            NotificationType::System,
            $notification->getType()
        );

        self::assertSame(
            'Adresse de livraison enregistrée',
            $notification->getTitle()
        );

        self::assertSame(
            '/profil/adresses',
            $notification->getTargetUrl()
        );

        self::assertFalse(
            $notification->isRead()
        );
    }

    public function testUpdatingShippingAddressDoesNotCreateDuplicate(): void
    {
        $client = static::createClient();
        $this->installFrenchAddressLookupMock();

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
        $this->installFrenchAddressLookupMock();

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

        self::assertSame(
            2,
            $this->countNotifications(
                $userId
            )
        );

        $notification =
            $this->findLatestNotification(
                $userId
            );

        self::assertInstanceOf(
            Notification::class,
            $notification
        );

        self::assertSame(
            NotificationType::System,
            $notification->getType()
        );

        self::assertSame(
            'Adresse de facturation enregistrée',
            $notification->getTitle()
        );

        self::assertSame(
            'Votre adresse de facturation a bien été enregistrée.',
            $notification->getMessage()
        );

        self::assertSame(
            '/profil/adresses',
            $notification->getTargetUrl()
        );

        self::assertFalse(
            $notification->isRead()
        );
    }

    public function testInvalidAddressIsRejected(): void
    {
        $client = static::createClient();
        $this->installFrenchAddressLookupMock();

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

    public function testFrenchAddressRejectsInconsistentCitySpelling(): void
    {
        $client = static::createClient();
        $this->installFrenchAddressLookupMock();

        $user = $this->createUser(
            'address-test-city-spelling@shopwho.local',
            'Maxime',
            'Falchero'
        );

        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/profil/adresses'
        );

        $form = $crawler
            ->filter(
                'form[name="shipping_address"]'
            )
            ->form();

        $client->submit($form, [
            'shipping_address[firstName]' => 'Maxime',
            'shipping_address[lastName]' => 'Falchero',
            'shipping_address[line1]' => '10 rue des Tests',
            'shipping_address[line2]' => '',
            'shipping_address[postalCode]' => '63000',
            'shipping_address[city]' => 'Clermont-Fyrand',
            'shipping_address[countryCode]' => 'FR',
        ]);

        self::assertResponseStatusCodeSame(422);

        self::assertSelectorTextContains(
            'body',
            'La ville « Clermont-Fyrand » ne correspond pas au code postal 63000.'
        );

        self::assertSame(
            0,
            $this->countAddresses(
                $user->getId(),
                AddressType::Shipping
            )
        );
    }

    public function testAddressPageExposesAutocompleteControls(): void
    {
        $client = static::createClient();
        $this->installFrenchAddressLookupMock();

        $user = $this->createUser(
            'address-test-autocomplete-ui@shopwho.local',
            'Maxime',
            'Falchero'
        );

        $client->loginUser($user);

        $client->request(
            'GET',
            '/profil/adresses'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorCount(
            2,
            'form[data-address-autocomplete]'
        );

        self::assertSelectorCount(
            2,
            '[data-address-feedback]'
        );

        self::assertSelectorCount(
            2,
            '[data-address-suggestions]'
        );

        self::assertSelectorExists(
            'script[src="/js/address-autocomplete.js"]'
        );
    }

    public function testAuthenticatedCustomerCanLookupAddressSuggestions(): void
    {
        $client = static::createClient();
        $this->installFrenchAddressLookupMock();

        $user = $this->createUser(
            'address-test-autocomplete-api@shopwho.local',
            'Maxime',
            'Falchero'
        );

        $client->loginUser($user);

        $client->request(
            'GET',
            '/profil/adresses/recherche',
            [
                'q' => '10 rue des Tests 63000 Clermont-Ferrand',
            ]
        );

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');

        $payload = json_decode(
            $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertTrue(
            $payload['available']
        );

        self::assertCount(
            1,
            $payload['suggestions']
        );

        self::assertSame(
            'Adresse 63000 Clermont-Ferrand',
            $payload['suggestions'][0]['label']
        );

        self::assertSame(
            '63000',
            $payload['suggestions'][0]['postalCode']
        );

        self::assertSame(
            'Clermont-Ferrand',
            $payload['suggestions'][0]['city']
        );
    }

    public function testShippingAddressCanAlsoBeUsedForBilling(): void
    {
        $client = static::createClient();
        $this->installFrenchAddressLookupMock();

        $user = $this->createUser(
            'address-test-copy-create@shopwho.local',
            'Maxime',
            'Falchero'
        );

        $client->loginUser($user);

        $crawler = $client->request(
            'GET',
            '/profil/adresses'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            'input[name="copy_shipping_to_billing"]'
            . '[type="checkbox"]'
            . '[value="1"]'
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
            'copy_shipping_to_billing' => '1',
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

        self::assertNotSame(
            (int) $shipping['id'],
            (int) $billing['id']
        );

        self::assertSame(
            'Maxime',
            $billing['first_name']
        );
        self::assertSame(
            'Falchero',
            $billing['last_name']
        );
        self::assertSame(
            '10 rue des Tests',
            $billing['line1']
        );
        self::assertSame(
            'Appartement 42',
            $billing['line2']
        );
        self::assertSame(
            '63000',
            $billing['postal_code']
        );
        self::assertSame(
            'Clermont-Ferrand',
            $billing['city']
        );
        self::assertSame(
            'FR',
            $billing['country_code']
        );

        self::assertSame(
            $shipping['first_name'],
            $billing['first_name']
        );
        self::assertSame(
            $shipping['last_name'],
            $billing['last_name']
        );
        self::assertSame(
            $shipping['line1'],
            $billing['line1']
        );
        self::assertSame(
            $shipping['line2'],
            $billing['line2']
        );
        self::assertSame(
            $shipping['postal_code'],
            $billing['postal_code']
        );
        self::assertSame(
            $shipping['city'],
            $billing['city']
        );
        self::assertSame(
            $shipping['country_code'],
            $billing['country_code']
        );

        /*
         * La copie livraison -> facturation est une seule
         * action utilisateur : elle ne doit donc produire
         * qu'une seule notification.
         */
        self::assertSame(
            1,
            $this->countNotifications(
                $userId
            )
        );

        $notification =
            $this->findLatestNotification(
                $userId
            );

        self::assertInstanceOf(
            Notification::class,
            $notification
        );

        self::assertSame(
            NotificationType::System,
            $notification->getType()
        );

        self::assertSame(
            'Adresses enregistrées',
            $notification->getTitle()
        );

        self::assertSame(
            'Vos adresses de livraison et de facturation ont bien été enregistrées.',
            $notification->getMessage()
        );

        self::assertSame(
            '/profil/adresses',
            $notification->getTargetUrl()
        );

        self::assertFalse(
            $notification->isRead()
        );
    }

    public function testCopyingShippingAddressUpdatesExistingBillingAddress(): void
    {
        $client = static::createClient();
        $this->installFrenchAddressLookupMock();

        $user = $this->createUser(
            'address-test-copy-update@shopwho.local',
            'Maxime',
            'Falchero'
        );

        $this->createAddress(
            $user,
            AddressType::Billing,
            '2 rue Ancienne Facturation',
            '75001',
            'Paris',
            'FR'
        );

        $userId = $user->getId();

        $initialBilling = $this->findAddress(
            $userId,
            AddressType::Billing
        );

        self::assertNotNull($initialBilling);

        $initialBillingId = (int) $initialBilling['id'];

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
            'shipping_address[line1]' => '99 avenue Nouvelle',
            'shipping_address[line2]' => '',
            'shipping_address[postalCode]' => '69001',
            'shipping_address[city]' => 'Lyon',
            'shipping_address[countryCode]' => 'FR',
            'copy_shipping_to_billing' => '1',
        ]);

        self::assertResponseRedirects('/profil/adresses');

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

        $billing = $this->findAddress(
            $userId,
            AddressType::Billing
        );

        self::assertNotNull($billing);

        self::assertSame(
            $initialBillingId,
            (int) $billing['id']
        );

        self::assertSame(
            '99 avenue Nouvelle',
            $billing['line1']
        );

        self::assertNull(
            $billing['line2']
        );

        self::assertSame(
            '69001',
            $billing['postal_code']
        );

        self::assertSame(
            'Lyon',
            $billing['city']
        );
    }

    public function testDeletingAccountDeletesCustomerAddresses(): void
    {
        $client = static::createClient();
        $this->installFrenchAddressLookupMock();

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

    private function installFrenchAddressLookupMock(): void
    {
        $httpClient = new MockHttpClient(
            static function (
                string $method,
                string $url
            ): MockResponse {
                self::assertSame('GET', $method);

                $query = [];

                parse_str(
                    (string) parse_url(
                        $url,
                        PHP_URL_QUERY
                    ),
                    $query
                );

                $search = (string) ($query['q'] ?? '');

                if (!preg_match(
                    '/\\b(\\d{5})\\s+(.+)$/u',
                    $search,
                    $matches
                )) {
                    return new MockResponse(
                        '{"features":[]}',
                        [
                            'http_code' => 200,
                            'response_headers' => [
                                'content-type: application/json',
                            ],
                        ]
                    );
                }

                $postalCode = $matches[1];
                $requestedCity = trim($matches[2]);

                /*
                 * Simulation explicite de la faute remontée
                 * pendant les tests utilisateurs.
                 */
                $resolvedCity = $requestedCity
                    === 'Clermont-Fyrand'
                    ? 'Clermont-Ferrand'
                    : $requestedCity;

                return new MockResponse(
                    json_encode(
                        [
                            'features' => [
                                [
                                    'properties' => [
                                        'label' => sprintf(
                                            'Adresse %s %s',
                                            $postalCode,
                                            $resolvedCity
                                        ),
                                        'name' => 'Adresse de test',
                                        'postcode' => $postalCode,
                                        'city' => $resolvedCity,
                                        'citycode' => 'TEST',
                                        'type' => 'housenumber',
                                        'score' => 0.99,
                                    ],
                                ],
                            ],
                        ],
                        JSON_THROW_ON_ERROR
                    ),
                    [
                        'http_code' => 200,
                        'response_headers' => [
                            'content-type: application/json',
                        ],
                    ]
                );
            }
        );

        static::getContainer()->set(
            FrenchAddressLookup::class,
            new FrenchAddressLookup($httpClient)
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

    private function findLatestNotification(
        ?int $userId
    ): ?Notification {
        if ($userId === null) {
            return null;
        }

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $user = $em->find(
            User::class,
            $userId
        );

        if (!$user instanceof User) {
            return null;
        }

        return $em
            ->getRepository(Notification::class)
            ->findOneBy(
                [
                    'user' => $user,
                ],
                [
                    'id' => 'DESC',
                ]
            );
    }

    private function countNotifications(
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
                    FROM notification
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
