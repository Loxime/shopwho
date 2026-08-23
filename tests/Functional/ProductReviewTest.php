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
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class ProductReviewTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testPurchasedProductAllowsReviewCreation(): void
    {
        $client = static::createClient();
        [$product, $user] = $this->fixture();
        $this->purchase($user, $product);
        $client->loginUser($user);
        $client->request('GET', '/produit/'.$product->getSlug());
        $client->submitForm('Publier mon avis', ['review[rating]' => 5, 'review[comment]' => '  Excellent produit.  ']);

        self::assertResponseRedirects('/produit/'.$product->getSlug());
        $review = $this->em()->getRepository(Review::class)->findOneBy(['user' => $user, 'product' => $product]);
        self::assertInstanceOf(Review::class, $review);
        self::assertSame(5, $review->getRating());
        self::assertSame('Excellent produit.', $review->getComment());
    }

    public function testUnpurchasedProductAndPurchaseTrackingAloneDoNotAllowCreation(): void
    {
        $client = static::createClient();
        [$product, $user] = $this->fixture();
        $event = (new TrackingEvent('visitor', 'session', 'PURCHASE', $product->getId()))->setUser($user);
        $this->em()->persist($event);
        $this->em()->flush();

        self::assertFalse($this->orders()->hasUserPurchasedProduct($user, $product));
        $client->loginUser($user);
        $client->request('POST', '/profil/avis/produit/'.$product->getId(), [
            'review' => ['rating' => 5, 'comment' => 'Frauduleux', '_token' => 'invalid'],
        ]);
        self::assertResponseRedirects('/produit/'.$product->getSlug());
        self::assertNull($this->reviews()->findOneByUserAndProduct($user, $product));
    }

    public function testUniqueDatabaseConstraintPreventsTwoReviewsPerProductAndUser(): void
    {
        self::bootKernel();
        [$product, $user] = $this->fixture();
        $em = $this->em();
        $em->persist(new Review($user, $product, 4));
        $em->flush();
        $em->persist(new Review($user, $product, 5));

        $this->expectException(UniqueConstraintViolationException::class);
        $em->flush();
    }

    public function testAnonymousCanReadReviewsButReviewRoutesRequireAuthentication(): void
    {
        $client = static::createClient();
        [$product, $user] = $this->fixture();
        $this->persistReview($user, $product, 4, 'Avis public');
        $client->request('GET', '/produit/'.$product->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Avis public');
        self::assertSelectorTextNotContains('body', $user->getEmail());
        $client->request('POST', '/profil/avis/produit/'.$product->getId());
        self::assertResponseRedirects('/connexion');
    }

    public function testOwnerCanEditReviewButOtherUserAndAdminGetNotFound(): void
    {
        $client = static::createClient();
        [$product, $owner] = $this->fixture();
        [, $other] = $this->fixture();
        [, $admin] = $this->fixture(['ROLE_ADMIN']);
        $review = $this->persistReview($owner, $product, 3, 'Avant');

        $client->loginUser($owner);
        $client->request('GET', '/profil/avis/'.$review->getId().'/modifier');
        $client->submitForm('Enregistrer', ['review[rating]' => 5, 'review[comment]' => 'Après']);
        self::assertResponseRedirects('/profil/avis');
        $review = $this->em()->find(Review::class, $review->getId());
        self::assertInstanceOf(Review::class, $review);
        self::assertSame(5, $review->getRating());
        self::assertSame('Après', $review->getComment());

        $client->loginUser($other);
        $client->request('GET', '/profil/avis/'.$review->getId().'/modifier');
        self::assertResponseStatusCodeSame(404);

        $client->loginUser($admin);
        $client->request('GET', '/profil/avis/'.$review->getId().'/modifier');
        self::assertResponseStatusCodeSame(404);
    }

    public function testOtherUserCannotDeleteAndInvalidCsrfKeepsReview(): void
    {
        $client = static::createClient();
        [$product, $owner] = $this->fixture();
        [, $other] = $this->fixture();
        $review = $this->persistReview($owner, $product, 4, null);
        $id = $review->getId();

        $client->loginUser($other);
        $client->request('POST', '/profil/avis/'.$id.'/supprimer', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(404);
        self::assertNotNull($this->em()->find(Review::class, $id));

        $client->loginUser($owner);
        $client->request('POST', '/profil/avis/'.$id.'/supprimer', ['_token' => 'invalid']);
        self::assertResponseRedirects('/profil/avis');
        self::assertNotNull($this->em()->find(Review::class, $id));
    }

    public function testRatingStatsForNoReviewsAndThreeReviews(): void
    {
        self::bootKernel();
        [$product, $userA] = $this->fixture();
        [, $userB] = $this->fixture();
        [, $userC] = $this->fixture();
        self::assertSame(['average' => null, 'count' => 0], $this->reviews()->getProductRatingStats($product));
        $this->persistReview($userA, $product, 5, null);
        $this->persistReview($userB, $product, 4, null);
        $this->persistReview($userC, $product, 3, null);

        self::assertSame(['average' => 4.0, 'count' => 3], $this->reviews()->getProductRatingStats($product));
    }

    public function testProductPageDisplaysAverageAndReviewCount(): void
    {
        $client = static::createClient();
        [$product, $userA] = $this->fixture();
        [, $userB] = $this->fixture();
        $this->persistReview($userA, $product, 5, null);
        $this->persistReview($userB, $product, 4, null);
        $client->request('GET', '/produit/'.$product->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.product-rating', '4,5/5 — 2 avis');
    }

    public function testMyReviewsOnlyContainsCurrentUsersReviewsInDescendingOrder(): void
    {
        $client = static::createClient();
        [$productA, $userA] = $this->fixture();
        [$productB, $userB] = $this->fixture();
        $older = $this->persistReview($userA, $productA, 3, 'Plus ancien');
        $newer = $this->persistReview($userA, $productB, 5, 'Plus récent');
        $foreign = $this->persistReview($userB, $productA, 1, 'Avis étranger');
        $this->em()->getConnection()->executeStatement('UPDATE product_review SET created_at = :date WHERE id = :id', ['date' => '2026-08-20 10:00:00', 'id' => $older->getId()]);
        $this->em()->getConnection()->executeStatement('UPDATE product_review SET created_at = :date WHERE id = :id', ['date' => '2026-08-22 10:00:00', 'id' => $newer->getId()]);
        $this->em()->clear();
        $client->loginUser($userA);
        $crawler = $client->request('GET', '/profil/avis');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.review-card'));
        self::assertSame((string) $newer->getId(), $crawler->filter('.review-card')->eq(0)->attr('data-review-id'));
        self::assertSame((string) $older->getId(), $crawler->filter('.review-card')->eq(1)->attr('data-review-id'));
        self::assertStringNotContainsString((string) $foreign->getComment(), $crawler->text());
    }

    public function testAdminCannotDeleteProductContainingReview(): void
    {
        $client = static::createClient();
        [$product, $owner] = $this->fixture();
        [, $admin] = $this->fixture(['ROLE_ADMIN']);
        $this->persistReview($owner, $product, 5, null);
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/products');
        $form = $crawler->filter(sprintf('form[action="/admin/products/%d"]', $product->getId()))->form();
        $client->submit($form);
        self::assertResponseRedirects('/admin/products');
        $client->followRedirect();
        self::assertSelectorTextContains('.admin-flash-error', 'possède des avis clients');
        self::assertNotNull($this->em()->find(Product::class, $product->getId()));
    }

    /** @return array{Product, User} */
    private function fixture(array $roles = []): array
    {
        $suffix = bin2hex(random_bytes(6));
        $category = (new Category())->setName('Cat '.$suffix)->setSlug('cat-'.$suffix);
        $product = (new Product())->setName('Produit '.$suffix)->setSlug('produit-'.$suffix)->setDescription('Test')->setPriceCents(1000)->setStock(5)->setCategory($category);
        $user = (new User())->setEmail('user-'.$suffix.'@example.test')->setFirstName('Alice')->setLastName('Martin')->setPassword('unused')->setRoles($roles);
        $this->em()->persist($category); $this->em()->persist($product); $this->em()->persist($user); $this->em()->flush();
        return [$product, $user];
    }

    private function purchase(User $user, Product $product): void
    {
        $order = new Order($user, 'SW-REVIEW-'.bin2hex(random_bytes(6)), $product->getPriceCents());
        OrderItem::fromProduct($order, $product, 1);
        $this->em()->persist($order); $this->em()->flush();
    }

    private function persistReview(User $user, Product $product, int $rating, ?string $comment): Review
    {
        $review = (new Review($user, $product, $rating))->setComment($comment);
        $this->em()->persist($review); $this->em()->flush();
        return $review;
    }

    private function em(): EntityManagerInterface { return static::getContainer()->get(EntityManagerInterface::class); }
    private function reviews(): ReviewRepository { return static::getContainer()->get(ReviewRepository::class); }
    private function orders(): OrderRepository { return static::getContainer()->get(OrderRepository::class); }
}
