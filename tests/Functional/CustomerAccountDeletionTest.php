<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\TrackingEvent;
use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CustomerAccountDeletionTest extends WebTestCase
{
    private const EMAIL = 'account-deletion@shopwho.local';
    private const PASSWORD = 'Delete-Account-2026!';

    private const CATEGORY_SLUG = 'account-deletion-test';
    private const PRODUCT_SLUG = 'account-deletion-product';

    private const ORDER_REFERENCE = 'DELETE-ACCOUNT-ORDER';

    private const VISITOR_ID = 'delete-account-visitor';
    private const SESSION_ID = 'delete-account-session';

    private const UNRELATED_VISITOR_ID = 'unrelated-visitor';
    private const UNRELATED_SESSION_ID = 'unrelated-session';

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

    public function testAnonymousUserCannotAccessAccountDeletion(): void
    {
        $client = static::createClient();

        $client->request('GET', '/profil/supprimer');

        self::assertResponseRedirects('/connexion');
    }

    public function testWrongPasswordDoesNotDeleteAccount(): void
    {
        $client = static::createClient();

        $fixture = $this->createCompleteCustomerFixture();

        $client->loginUser($fixture['user']);

        $crawler = $client->request('GET', '/profil/supprimer');

        self::assertResponseIsSuccessful();

        $client->submit(
            $crawler
                ->selectButton('Supprimer définitivement mon compte')
                ->form([
                    'delete_account[currentPassword]' => 'Wrong-Password-2026!',
                    'delete_account[confirmation]' => 'SUPPRIMER',
                ])
        );

        self::assertResponseStatusCodeSame(422);

        self::assertSelectorTextContains(
            'body',
            'Le mot de passe actuel est incorrect.'
        );

        $this->assertFixtureStillExists($fixture);
    }

    public function testExplicitConfirmationIsRequired(): void
    {
        $client = static::createClient();

        $fixture = $this->createCompleteCustomerFixture();

        $client->loginUser($fixture['user']);

        $crawler = $client->request('GET', '/profil/supprimer');

        $client->submit(
            $crawler
                ->selectButton('Supprimer définitivement mon compte')
                ->form([
                    'delete_account[currentPassword]' => self::PASSWORD,
                    'delete_account[confirmation]' => 'supprimer',
                ])
        );

        self::assertResponseStatusCodeSame(422);

        self::assertSelectorTextContains(
            'body',
            'Saisissez exactement SUPPRIMER pour confirmer.'
        );

        $this->assertFixtureStillExists($fixture);
    }

    public function testCustomerCanDeleteAccountAndAssociatedData(): void
    {
        $client = static::createClient();

        $fixture = $this->createCompleteCustomerFixture();

        $client->loginUser($fixture['user']);

        /*
         * On passe par le vrai endpoint de consentement afin que
         * BrowserKit stocke le cookie exactement comme en production.
         */
        $client->request(
            'POST',
            '/tracking/consent/accept'
        );

        self::assertResponseRedirects('/');

        $consentCookie = $client
            ->getCookieJar()
            ->get('shopwho_tracking_consent');

        self::assertNotNull($consentCookie);
        self::assertSame('yes', $consentCookie->getValue());

        $crawler = $client->request('GET', '/profil/supprimer');

        $session = $client->getRequest()->getSession();
        $session->set('tracking_visitor_id', self::VISITOR_ID);
        $session->set('tracking_session_id', self::SESSION_ID);
        $session->save();

        $client->submit(
            $crawler
                ->selectButton('Supprimer définitivement mon compte')
                ->form([
                    'delete_account[currentPassword]' => self::PASSWORD,
                    'delete_account[confirmation]' => 'SUPPRIMER',
                ])
        );

        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('/');

        /*
         * Le cookie de consentement doit être expiré/supprimé.
         */
        self::assertNull(
            $client->getCookieJar()->get(
                'shopwho_tracking_consent'
            )
        );

        $this->assertFixtureDeleted($fixture);

        /*
         * On ne doit surtout pas supprimer les données
         * d'un autre visiteur.
         */
        self::assertSame(
            1,
            $this->countById(
                'tracking_event',
                $fixture['unrelatedTrackingEventId']
            )
        );

        /*
         * La session d'authentification doit également avoir disparu.
         */
        $client->followRedirect();

        self::assertResponseIsSuccessful();

        $client->request('GET', '/profil');

        self::assertResponseRedirects('/connexion');
    }

    /**
     * @return array{
     *     user: User,
     *     userId: int,
     *     orderId: int,
     *     orderItemId: int,
     *     reviewId: int,
     *     linkedTrackingEventId: int,
     *     anonymousTrackingEventId: int,
     *     unrelatedTrackingEventId: int
     * }
     */
    private function createCompleteCustomerFixture(): array
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(
            UserPasswordHasherInterface::class
        );

        $user = (new User())
            ->setEmail(self::EMAIL)
            ->setFirstName('Delete')
            ->setLastName('Me');

        $user->setPassword(
            $hasher->hashPassword(
                $user,
                self::PASSWORD
            )
        );

        $category = (new Category())
            ->setName('Account deletion test')
            ->setSlug(self::CATEGORY_SLUG);

        $product = (new Product())
            ->setName('Account deletion product')
            ->setSlug(self::PRODUCT_SLUG)
            ->setDescription('Produit utilisé pour tester la suppression.')
            ->setPriceCents(2499)
            ->setStock(10)
            ->setCategory($category);

        $em->persist($user);
        $em->persist($category);
        $em->persist($product);

        $em->flush();

        $order = new Order(
            $user,
            self::ORDER_REFERENCE,
            2499
        );

        $orderItem = OrderItem::fromProduct(
            $order,
            $product,
            1
        );

        $review = (new Review(
            $user,
            $product,
            5
        ))->setComment('Avis à supprimer avec le compte.');

        $linkedTrackingEvent = new TrackingEvent(
            self::VISITOR_ID,
            self::SESSION_ID,
            'PRODUCT_VIEW',
            $product->getId()
        );

        $linkedTrackingEvent->setUser($user);

        /*
         * Cet événement appartient au même visiteur/session,
         * mais reste volontairement sans user_id.
         */
        $anonymousTrackingEvent = new TrackingEvent(
            self::VISITOR_ID,
            self::SESSION_ID,
            'PAGE_VIEW'
        );

        /*
         * Celui-ci ne doit PAS être supprimé.
         */
        $unrelatedTrackingEvent = new TrackingEvent(
            self::UNRELATED_VISITOR_ID,
            self::UNRELATED_SESSION_ID,
            'PAGE_VIEW'
        );

        $em->persist($order);
        $em->persist($review);
        $em->persist($linkedTrackingEvent);
        $em->persist($anonymousTrackingEvent);
        $em->persist($unrelatedTrackingEvent);

        /*
         * OrderItem est persisté par cascade depuis Order.
         */
        $em->flush();

        return [
            'user' => $user,
            'userId' => $user->getId(),
            'orderId' => $order->getId(),
            'orderItemId' => $orderItem->getId(),
            'reviewId' => $review->getId(),
            'linkedTrackingEventId' => $linkedTrackingEvent->getId(),
            'anonymousTrackingEventId' => $anonymousTrackingEvent->getId(),
            'unrelatedTrackingEventId' => $unrelatedTrackingEvent->getId(),
        ];
    }

    /**
     * @param array{
     *     user: User,
     *     userId: int,
     *     orderId: int,
     *     orderItemId: int,
     *     reviewId: int,
     *     linkedTrackingEventId: int,
     *     anonymousTrackingEventId: int,
     *     unrelatedTrackingEventId: int
     * } $fixture
     */
    private function assertFixtureDeleted(array $fixture): void
    {
        self::assertSame(
            0,
            $this->countById(
                'app_user',
                $fixture['userId']
            )
        );

        self::assertSame(
            0,
            $this->countById(
                'customer_order',
                $fixture['orderId']
            )
        );

        self::assertSame(
            0,
            $this->countById(
                'order_item',
                $fixture['orderItemId']
            )
        );

        self::assertSame(
            0,
            $this->countById(
                'product_review',
                $fixture['reviewId']
            )
        );

        self::assertSame(
            0,
            $this->countById(
                'tracking_event',
                $fixture['linkedTrackingEventId']
            )
        );

        self::assertSame(
            0,
            $this->countById(
                'tracking_event',
                $fixture['anonymousTrackingEventId']
            )
        );
    }

    /**
     * @param array{
     *     user: User,
     *     userId: int,
     *     orderId: int,
     *     orderItemId: int,
     *     reviewId: int,
     *     linkedTrackingEventId: int,
     *     anonymousTrackingEventId: int,
     *     unrelatedTrackingEventId: int
     * } $fixture
     */
    private function assertFixtureStillExists(array $fixture): void
    {
        self::assertSame(
            1,
            $this->countById(
                'app_user',
                $fixture['userId']
            )
        );

        self::assertSame(
            1,
            $this->countById(
                'customer_order',
                $fixture['orderId']
            )
        );

        self::assertSame(
            1,
            $this->countById(
                'order_item',
                $fixture['orderItemId']
            )
        );

        self::assertSame(
            1,
            $this->countById(
                'product_review',
                $fixture['reviewId']
            )
        );

        self::assertSame(
            1,
            $this->countById(
                'tracking_event',
                $fixture['linkedTrackingEventId']
            )
        );

        self::assertSame(
            1,
            $this->countById(
                'tracking_event',
                $fixture['anonymousTrackingEventId']
            )
        );
    }

    private function countById(
        string $table,
        int $id
    ): int {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        return (int) $em
            ->getConnection()
            ->fetchOne(
                sprintf(
                    'SELECT COUNT(*) FROM %s WHERE id = :id',
                    $table
                ),
                ['id' => $id]
            );
    }

    private function cleanupFixtures(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $connection = $em->getConnection();

        /*
         * Nettoyage volontairement explicite afin que les tests
         * restent relançables même après un précédent échec.
         */
        $connection->executeStatement(
            'DELETE FROM tracking_event
             WHERE visitor_id IN (:visitor, :unrelated)',
            [
                'visitor' => self::VISITOR_ID,
                'unrelated' => self::UNRELATED_VISITOR_ID,
            ]
        );

        $connection->executeStatement(
            'DELETE FROM product_review
             WHERE user_id IN (
                 SELECT id
                 FROM app_user
                 WHERE email = :email
             )',
            ['email' => self::EMAIL]
        );

        $connection->executeStatement(
            'DELETE FROM order_item
             WHERE order_id IN (
                 SELECT id
                 FROM customer_order
                 WHERE reference = :reference
             )',
            ['reference' => self::ORDER_REFERENCE]
        );

        $connection->executeStatement(
            'DELETE FROM customer_order
             WHERE reference = :reference',
            ['reference' => self::ORDER_REFERENCE]
        );

        $connection->executeStatement(
            'DELETE FROM app_user
             WHERE email = :email',
            ['email' => self::EMAIL]
        );

        $connection->executeStatement(
            'DELETE FROM product
             WHERE slug = :slug',
            ['slug' => self::PRODUCT_SLUG]
        );

        $connection->executeStatement(
            'DELETE FROM category
             WHERE slug = :slug',
            ['slug' => self::CATEGORY_SLUG]
        );

        $em->clear();
    }
}
