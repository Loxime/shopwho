<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\SpecialOffer;
use App\Entity\TrackingEvent;
use App\Entity\User;
use App\Enum\SpecialOfferPlacement;
use App\Kernel;
use App\Repository\SpecialOfferRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\KernelInterface;

final class SpecialOfferFeatureTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private Connection $connection;

    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        /*
         * Toutes les requêtes du test utilisent
         * la même connexion Doctrine.
         */
        $this->client->disableReboot();

        $this->em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $this->connection =
            $this->em->getConnection();

        $this->connection
            ->beginTransaction();

        /*
         * On isole uniquement les offres pendant
         * le test. Le ROLLBACK restaurera ensuite
         * l'état original de shopwho_test.
         */
        $this->connection->executeStatement(
            'DELETE FROM special_offer'
        );
    }

    protected function tearDown(): void
    {
        if (
            $this->connection
                ->isTransactionActive()
        ) {
            $this->connection->rollBack();
        }

        $this->em->clear();

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testRepositoryFiltersDatesPlacementsAndPriority(): void
    {
        $now = new \DateTimeImmutable(
            '2026-08-27 12:00:00'
        );

        $header = $this->createOffer(
            'repo-header',
            SpecialOfferPlacement::Header,
            100
        );

        $both = $this->createOffer(
            'repo-both',
            SpecialOfferPlacement::Both,
            90
        );

        $homepage = $this->createOffer(
            'repo-homepage',
            SpecialOfferPlacement::Homepage,
            200
        );

        $expired = $this->createOffer(
            'repo-expired',
            SpecialOfferPlacement::Header,
            1000
        );

        $expired->setEndsAt(
            $now->modify('-1 minute')
        );

        $future = $this->createOffer(
            'repo-future',
            SpecialOfferPlacement::Header,
            1000
        );

        $future->setStartsAt(
            $now->modify('+1 minute')
        );

        $inactive = $this->createOffer(
            'repo-inactive',
            SpecialOfferPlacement::Header,
            1000
        );

        $inactive->setIsActive(false);

        $this->em->flush();

        $repository =
            $this->specialOfferRepository();

        $selectedHeader =
            $repository->findActiveHeaderOffer(
                $now
            );

        self::assertNotNull(
            $selectedHeader
        );

        self::assertSame(
            $header->getId(),
            $selectedHeader->getId()
        );

        $homepageOffers =
            $repository
                ->findActiveHomepageOffers(
                    8,
                    $now
                );

        $homepageIds = array_map(
            static fn (
                SpecialOffer $offer
            ): ?int => $offer->getId(),
            $homepageOffers
        );

        self::assertContains(
            $homepage->getId(),
            $homepageIds
        );

        self::assertContains(
            $both->getId(),
            $homepageIds
        );

        self::assertNotContains(
            $header->getId(),
            $homepageIds
        );

        self::assertNotContains(
            $expired->getId(),
            $homepageIds
        );

        self::assertNotContains(
            $future->getId(),
            $homepageIds
        );

        self::assertNotContains(
            $inactive->getId(),
            $homepageIds
        );
    }

    public function testHomepageRepositoryIsLimitedToEightOffers(): void
    {
        $expectedIds = [];

        for ($index = 0; $index < 9; ++$index) {
            $offer = $this->createOffer(
                'limit-'.$index,
                SpecialOfferPlacement::Homepage,
                1000 - $index
            );

            if ($index < 8) {
                $expectedIds[] =
                    $offer->getId();
            }
        }

        $offers = $this
            ->specialOfferRepository()
            ->findActiveHomepageOffers(
                99,
                new \DateTimeImmutable()
            );

        self::assertCount(
            8,
            $offers
        );

        self::assertSame(
            $expectedIds,
            array_map(
                static fn (
                    SpecialOffer $offer
                ): ?int =>
                    $offer->getId(),
                $offers
            )
        );
    }

    public function testAdminCanCreateSpecialOffer(): void
    {
        $admin = $this->createUser(
            true
        );

        $this->client->loginUser(
            $admin
        );

        $crawler =
            $this->client->request(
                'GET',
                '/admin/offers/new'
            );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton(
                'Enregistrer'
            )
            ->form([
                'special_offer[title]' =>
                    'Offre admin test',

                'special_offer[content]' =>
                    'Contenu créé depuis le backoffice.',

                'special_offer[ctaLabel]' =>
                    'Découvrir',

                'special_offer[targetUrl]' =>
                    '/?category=audio',

                'special_offer[placement]' =>
                    'homepage',

                'special_offer[backgroundColor]' =>
                    '#112233',

                'special_offer[textColor]' =>
                    '#FFFFFF',

                'special_offer[priority]' =>
                    '42',

                'special_offer[isActive]' =>
                    '1',

                'special_offer[experimentKey]' =>
                    'admin-test',

                'special_offer[experimentVariant]' =>
                    'A',
            ]);

        $this->client->submit($form);

        self::assertResponseRedirects(
            '/admin/offers'
        );

        $this->em->clear();

        $offer = $this
            ->specialOfferRepository()
            ->findOneBy([
                'title' =>
                    'Offre admin test',
            ]);

        self::assertInstanceOf(
            SpecialOffer::class,
            $offer
        );

        self::assertSame(
            SpecialOfferPlacement::Homepage,
            $offer->getPlacement()
        );

        self::assertSame(
            42,
            $offer->getPriority()
        );

        self::assertSame(
            '#112233',
            $offer->getBackgroundColor()
        );

        self::assertSame(
            'admin-test',
            $offer->getExperimentKey()
        );

        self::assertSame(
            'A',
            $offer->getExperimentVariant()
        );
    }

    public function testAdminRejectsEndDateBeforeStartDate(): void
    {
        $admin = $this->createUser(
            true
        );

        $this->client->loginUser(
            $admin
        );

        $crawler =
            $this->client->request(
                'GET',
                '/admin/offers/new'
            );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton(
                'Enregistrer'
            )
            ->form([
                'special_offer[title]' =>
                    'Dates invalides',

                'special_offer[content]' =>
                    'Cette offre ne doit pas être créée.',

                'special_offer[placement]' =>
                    'homepage',

                'special_offer[backgroundColor]' =>
                    '#272785',

                'special_offer[textColor]' =>
                    '#FFFFFF',

                'special_offer[priority]' =>
                    '1',

                'special_offer[startsAt]' =>
                    '2026-08-28T12:00',

                'special_offer[endsAt]' =>
                    '2026-08-27T12:00',

                'special_offer[isActive]' =>
                    '1',
            ]);

        $this->client->submit(
            $form
        );

        self::assertNotSame(
            302,
            $this->client
                ->getResponse()
                ->getStatusCode()
        );

        self::assertStringContainsString(
            'La fin de diffusion doit être postérieure au début.',
            (string) $this->client
                ->getResponse()
                ->getContent()
        );

        self::assertNull(
            $this->specialOfferRepository()
                ->findOneBy([
                    'title' =>
                        'Dates invalides',
                ])
        );
    }

    public function testStorefrontDisplaysHeaderAndOnlyEightHomepageOffers(): void
    {
        $header = $this->createOffer(
            'header-visible',
            SpecialOfferPlacement::Header,
            100
        );

        $homepageOffers = [];

        for ($index = 0; $index < 9; ++$index) {
            $homepageOffers[] =
                $this->createOffer(
                    'homepage-'.$index,
                    SpecialOfferPlacement::Homepage,
                    1000 - $index
                );
        }

        $crawler =
            $this->client->request(
                'GET',
                '/'
            );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.special-offer-banner',
            $header->getTitle()
        );

        self::assertSelectorCount(
            3,
            '.special-offers-primary .special-offer-card'
        );

        self::assertSelectorCount(
            5,
            '.special-offers-secondary .special-offer-card'
        );

        self::assertSelectorCount(
            8,
            '.special-offers-section .special-offer-card'
        );

        self::assertStringContainsString(
            $homepageOffers[0]
                ->getTitle(),
            (string) $this->client
                ->getResponse()
                ->getContent()
        );

        self::assertStringNotContainsString(
            $homepageOffers[8]
                ->getTitle(),
            (string) $this->client
                ->getResponse()
                ->getContent()
        );
    }

    public function testSpecialOfferTrackingStoresTrustedExperimentMetadata(): void
    {
        $category =
            $this->createCategory();

        $offer = $this->createOffer(
            'tracking',
            SpecialOfferPlacement::Both,
            100
        );

        $offer
            ->setTargetCategory(
                $category
            )
            ->setExperimentKey(
                'homepage-banner-test'
            )
            ->setExperimentVariant(
                'B'
            );

        $this->em->flush();

        $user = $this->createUser();

        $this->client->loginUser(
            $user
        );

        $this->client
            ->getCookieJar()
            ->set(
                new Cookie(
                    'shopwho_tracking_consent',
                    'yes'
                )
            );

        $crawler =
            $this->client->request(
                'GET',
                '/'
            );

        self::assertResponseIsSuccessful();

        $trackedOffer = $crawler->filter(
            sprintf(
                '[data-special-offer]'
                .'[data-offer-id="%d"]'
                .'[data-placement="homepage"]',
                $offer->getId()
            )
        );

        self::assertCount(
            1,
            $trackedOffer
        );

        $token =
            $trackedOffer->attr(
                'data-tracking-token'
            );

        self::assertNotNull(
            $token
        );

        self::assertNotSame(
            '',
            $token
        );

        $this->client->jsonRequest(
            'POST',
            '/tracking/special-offers',
            [
                '_token' => $token,
                'sourcePath' =>
                    '/?campaign=test',
                'events' => [
                    [
                        'eventType' =>
                            'SPECIAL_OFFER_IMPRESSION',
                        'offerId' =>
                            $offer->getId(),
                        'placement' =>
                            'homepage',
                        'position' => 1,
                    ],
                    [
                        'eventType' =>
                            'SPECIAL_OFFER_CLICK',
                        'offerId' =>
                            $offer->getId(),
                        'placement' =>
                            'homepage',
                        'position' => 1,
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(
            202
        );

        $events =
            $this->specialOfferEvents(
                $offer
            );

        self::assertCount(
            2,
            $events
        );

        self::assertSame(
            'SPECIAL_OFFER_IMPRESSION',
            $events[0]->getEventType()
        );

        self::assertSame(
            'SPECIAL_OFFER_CLICK',
            $events[1]->getEventType()
        );

        self::assertSame(
            $user->getId(),
            $events[0]
                ->getUser()
                ?->getId()
        );

        self::assertSame(
            $events[0]->getVisitorId(),
            $events[1]->getVisitorId()
        );

        self::assertSame(
            $events[0]->getSessionId(),
            $events[1]->getSessionId()
        );

        $metadata =
            $events[0]->getMetadata();

        self::assertSame(
            $offer->getId(),
            $metadata['offer_id']
        );

        self::assertSame(
            'homepage',
            $metadata['placement']
        );

        self::assertSame(
            1,
            $metadata['position']
        );

        self::assertSame(
            'both',
            $metadata[
                'configured_placement'
            ]
        );

        self::assertSame(
            $category->getSlug(),
            $metadata[
                'target_category'
            ]
        );

        self::assertSame(
            'homepage-banner-test',
            $metadata[
                'experiment_key'
            ]
        );

        self::assertSame(
            'B',
            $metadata[
                'experiment_variant'
            ]
        );

        self::assertSame(
            '/?campaign=test',
            $metadata['source_path']
        );
    }

    public function testRefusedConsentStoresNoSpecialOfferEvent(): void
    {
        $offer = $this->createOffer(
            'tracking-refused',
            SpecialOfferPlacement::Homepage,
            100
        );

        $this->client
            ->getCookieJar()
            ->set(
                new Cookie(
                    'shopwho_tracking_consent',
                    'yes'
                )
            );

        $crawler =
            $this->client->request(
                'GET',
                '/'
            );

        self::assertResponseIsSuccessful();

        $node = $crawler->filter(
            sprintf(
                '[data-special-offer]'
                .'[data-offer-id="%d"]'
                .'[data-placement="homepage"]',
                $offer->getId()
            )
        );

        self::assertCount(
            1,
            $node
        );

        $token =
            $node->attr(
                'data-tracking-token'
            );

        self::assertNotNull(
            $token
        );

        $this->client
            ->getCookieJar()
            ->set(
                new Cookie(
                    'shopwho_tracking_consent',
                    'no'
                )
            );

        $this->client->jsonRequest(
            'POST',
            '/tracking/special-offers',
            [
                '_token' => $token,
                'events' => [
                    [
                        'eventType' =>
                            'SPECIAL_OFFER_IMPRESSION',
                        'offerId' =>
                            $offer->getId(),
                        'placement' =>
                            'homepage',
                        'position' => 1,
                    ],
                ],
            ]
        );

        self::assertResponseStatusCodeSame(
            202
        );

        self::assertCount(
            0,
            $this->specialOfferEvents(
                $offer
            )
        );
    }

    private function createOffer(
        string $suffix,
        SpecialOfferPlacement $placement,
        int $priority
    ): SpecialOffer {
        $offer = (new SpecialOffer())
            ->setTitle(
                'special-offer-test-'.$suffix
            )
            ->setContent(
                'Contenu de test '.$suffix
            )
            ->setPlacement(
                $placement
            )
            ->setPriority(
                $priority
            )
            ->setBackgroundColor(
                '#272785'
            )
            ->setTextColor(
                '#FFFFFF'
            )
            ->setIsActive(
                true
            );

        $this->em->persist(
            $offer
        );

        $this->em->flush();

        return $offer;
    }

    private function createCategory():
        Category
    {
        $suffix = bin2hex(
            random_bytes(5)
        );

        $category = (new Category())
            ->setName(
                'Special offer '.$suffix
            )
            ->setSlug(
                'special-offer-test-'.$suffix
            );

        $this->em->persist(
            $category
        );

        $this->em->flush();

        return $category;
    }

    private function createUser(
        bool $admin = false
    ): User {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $user = (new User())
            ->setEmail(
                'special-offer-test-'
                .$suffix
                .'@example.test'
            )
            ->setFirstName('Special')
            ->setLastName('Offer')
            ->setPassword('unused');

        if ($admin) {
            $user->setRoles([
                'ROLE_ADMIN',
            ]);
        }

        $this->em->persist(
            $user
        );

        $this->em->flush();

        return $user;
    }

    /**
     * @return list<TrackingEvent>
     */
    private function specialOfferEvents(
        SpecialOffer $offer
    ): array {
        $this->em->clear();

        $events = $this->em
            ->getRepository(
                TrackingEvent::class
            )
            ->createQueryBuilder('event')
            ->andWhere(
                'event.eventType IN (:types)'
            )
            ->setParameter(
                'types',
                [
                    'SPECIAL_OFFER_IMPRESSION',
                    'SPECIAL_OFFER_CLICK',
                ]
            )
            ->orderBy(
                'event.id',
                'ASC'
            )
            ->getQuery()
            ->getResult();

        return array_values(
            array_filter(
                $events,
                static function (
                    TrackingEvent $event
                ) use ($offer): bool {
                    return (
                        $event
                            ->getMetadata()[
                                'offer_id'
                            ]
                        ?? null
                    ) === $offer->getId();
                }
            )
        );
    }

    private function specialOfferRepository():
        SpecialOfferRepository
    {
        return static::getContainer()
            ->get(
                SpecialOfferRepository::class
            );
    }
}
