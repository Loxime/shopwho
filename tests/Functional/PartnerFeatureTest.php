<?php

namespace App\Tests\Functional;

use App\Entity\Partner;
use App\Entity\User;
use App\Kernel;
use App\Repository\PartnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class PartnerFeatureTest extends WebTestCase
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

        $connection = static::getContainer()
            ->get(EntityManagerInterface::class)
            ->getConnection();

        $connection->executeStatement(
            "
                DELETE FROM partner
                WHERE name LIKE
                    'Partner Feature %'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM app_user
                WHERE email LIKE
                    'partner-feature-%@shopwho.test'
            "
        );

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testOnlyActivePartnersAreDisplayed(): void
    {
        $client = static::createClient();

        $active = $this->createPartner(
            'Active',
            true,
            100
        );

        $inactive = $this->createPartner(
            'Inactive',
            false,
            200
        );

        $client->request(
            'GET',
            '/partenaires'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.partners-grid',
            $active->getName()
        );

        self::assertSelectorTextNotContains(
            '.partners-grid',
            $inactive->getName()
        );
    }

    public function testPartnersAreOrderedByPriority(): void
    {
        $client = static::createClient();

        $low = $this->createPartner(
            'Low Priority',
            true,
            10
        );

        $high = $this->createPartner(
            'High Priority',
            true,
            100
        );

        $crawler = $client->request(
            'GET',
            '/partenaires'
        );

        self::assertResponseIsSuccessful();

        $names = $crawler
            ->filter(
                '.partner-card h2'
            )
            ->each(
                static fn ($node) =>
                    trim($node->text())
            );

        $highPosition = array_search(
            $high->getName(),
            $names,
            true
        );

        $lowPosition = array_search(
            $low->getName(),
            $names,
            true
        );

        self::assertIsInt(
            $highPosition
        );

        self::assertIsInt(
            $lowPosition
        );

        self::assertLessThan(
            $lowPosition,
            $highPosition
        );
    }

    public function testPartnerPageProvidesSeoMetadata(): void
    {
        $client = static::createClient();

        $crawler = $client->request(
            'GET',
            '/partenaires'
        );

        self::assertResponseIsSuccessful();

        self::assertSame(
            'http://localhost/partenaires',
            $crawler
                ->filter(
                    'link[rel="canonical"]'
                )
                ->attr('href')
        );

        self::assertSelectorExists(
            'meta[name="description"]'
        );
    }

    public function testMarketingManagerCanCreatePartner(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createUser(
                ['ROLE_MARKETING_MANAGER']
            )
        );

        $crawler = $client->request(
            'GET',
            '/admin/partners/new'
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton(
                'Enregistrer'
            )
            ->form();

        $client->submit(
            $form,
            [
                'partner[name]' =>
                    'Partner Feature Création',
                'partner[description]' =>
                    'Partenaire créé par test.',
                'partner[websiteUrl]' =>
                    'https://example.com/partner',
                'partner[logoUrl]' =>
                    'https://example.com/logo.png',
                'partner[priority]' =>
                    '50',
                'partner[isActive]' =>
                    '1',
            ]
        );

        self::assertResponseRedirects(
            '/admin/partners'
        );

        $partner = static::getContainer()
            ->get(PartnerRepository::class)
            ->findOneBy([
                'name' =>
                    'Partner Feature Création',
            ]);

        self::assertNotNull(
            $partner
        );

        self::assertTrue(
            $partner->isActive()
        );

        self::assertSame(
            50,
            $partner->getPriority()
        );
    }

    public function testCustomerCannotAccessPartnerAdministration(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createUser(
                ['ROLE_USER']
            )
        );

        $client->request(
            'GET',
            '/admin/partners'
        );

        self::assertResponseStatusCodeSame(
            403
        );
    }

    public function testPartnerPageIsPresentInSitemap(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/sitemap.xml'
        );

        self::assertResponseIsSuccessful();

        $content =
            (string) $client
                ->getResponse()
                ->getContent();

        self::assertStringContainsString(
            'http://localhost/partenaires',
            $content
        );
    }

    private function createPartner(
        string $suffix,
        bool $active,
        int $priority
    ): Partner {
        $partner = (new Partner())
            ->setName(
                'Partner Feature '.$suffix
            )
            ->setDescription(
                'Description '.$suffix.'.'
            )
            ->setWebsiteUrl(
                'https://example.com/'
                .strtolower(
                    str_replace(
                        ' ',
                        '-',
                        $suffix
                    )
                )
            )
            ->setLogoUrl(
                'https://example.com/logo.png'
            )
            ->setIsActive(
                $active
            )
            ->setPriority(
                $priority
            );

        $em = static::getContainer()
            ->get(EntityManagerInterface::class);

        $em->persist(
            $partner
        );

        $em->flush();

        return $partner;
    }

    private function createUser(
        array $roles
    ): User {
        $user = (new User())
            ->setEmail(
                'partner-feature-'
                .bin2hex(random_bytes(6))
                .'@shopwho.test'
            )
            ->setFirstName('Partner')
            ->setLastName('Tester')
            ->setPassword('unused')
            ->setRoles($roles);

        $em = static::getContainer()
            ->get(EntityManagerInterface::class);

        $em->persist(
            $user
        );

        $em->flush();

        return $user;
    }
}
