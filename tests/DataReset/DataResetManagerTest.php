<?php

namespace App\Tests\DataReset;

use App\DataReset\DataResetManager;
use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\TrackingEvent;
use App\Entity\User;
use App\Enum\DataOrigin;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class DataResetManagerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DataResetManager $manager;
    private ?Category $category = null;
    private string $suffix;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->manager = static::getContainer()->get(DataResetManager::class);
        $this->category = null;
        $this->suffix = strtoupper(bin2hex(random_bytes(6)));
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    public function testUserDryRunAndApplyRespectNativeOrdersReviewsAndTracking(): void
    {
        $deletable = $this->user('DELETE', DataOrigin::Imported);
        $native = $this->user('NATIVE', DataOrigin::Native);
        $withOrder = $this->user('ORDER', DataOrigin::Imported);
        $withReview = $this->user('REVIEW', DataOrigin::Imported);
        $tracking = (new TrackingEvent('visitor-'.$this->suffix, 'session-'.$this->suffix, 'view'))->setUser($deletable);
        $product = $this->product('REVIEW-TARGET', DataOrigin::Imported);
        $this->em->persist(new Order($withOrder, 'ORDER-'.$this->suffix, 100));
        $this->em->persist(new Review($withReview, $product, 5));
        $this->em->persist($tracking);
        $this->em->flush();
        $refs = [$deletable->getExternalRef(), $native->getExternalRef(), $withOrder->getExternalRef(), $withReview->getExternalRef(), 'USR-FICTION-404-'.$this->suffix];

        $preview = $this->manager->previewReferences('users', $refs);
        self::assertSame([5, 1, 0, 3, 1], [$preview->getTotal(), $preview->getDeletable(), $preview->getDeleted(), $preview->getProtected(), $preview->getNotFound()]);
        self::assertNotNull($this->em->getRepository(User::class)->find($deletable->getId()));
        self::assertSame(['native', 'has_orders', 'has_reviews'], array_values(array_map(static fn ($entry) => $entry->reason, array_filter($preview->entries, static fn ($entry) => 'protected' === $entry->status))));

        $deletableId = $deletable->getId();
        $trackingId = $tracking->getId();
        $applied = $this->manager->applyReferences('users', $refs);
        self::assertSame([1, 1, 3, 1], [$applied->getDeletable(), $applied->getDeleted(), $applied->getProtected(), $applied->getNotFound()]);
        $this->em->clear();
        self::assertNull($this->em->getRepository(User::class)->find($deletableId));
        self::assertNotNull($event = $this->em->getRepository(TrackingEvent::class)->find($trackingId));
        self::assertNull($event->getUser());
    }

    public function testProductPolicyProtectsAllOrderHistoryAndReviews(): void
    {
        $deletable = $this->product('DELETE', DataOrigin::Imported);
        $native = $this->product('NATIVE', DataOrigin::Native);
        $linked = $this->product('LINKED', DataOrigin::Imported);
        $idSnapshot = $this->product('ID-SNAPSHOT', DataOrigin::Imported);
        $refSnapshot = $this->product('REF-SNAPSHOT', DataOrigin::Imported);
        $reviewed = $this->product('REVIEWED', DataOrigin::Imported);
        $user = $this->user('PRODUCT-HISTORY', DataOrigin::Imported);
        $this->em->flush();
        $order = new Order($user, 'PRODUCT-ORDER-'.$this->suffix, 400);
        OrderItem::fromProduct($order, $linked, 1);
        OrderItem::fromProduct($order, $idSnapshot, 1);
        // Preserve only the numeric snapshot path.
        $this->em->persist($order);
        $this->em->persist(new Review($user, $reviewed, 4));
        $this->em->flush();
        $this->em->getConnection()->executeStatement('UPDATE order_item SET product_id = NULL, product_external_ref_snapshot = NULL WHERE product_id_snapshot = ?', [$idSnapshot->getId()]);
        $snapshotOrder = new Order($user, 'SNAPSHOT-ORDER-'.$this->suffix, 100);
        OrderItem::import($snapshotOrder, null, $refSnapshot->getExternalRef(), 'Produit FICTION historique', 'fiction-historique', 1, 100);
        $this->em->persist($snapshotOrder);
        $this->em->flush();

        $refs = array_map(static fn (Product $product) => $product->getExternalRef(), [$deletable, $native, $linked, $idSnapshot, $refSnapshot, $reviewed]);
        $preview = $this->manager->previewReferences('products', $refs);
        self::assertSame([6, 1, 5], [$preview->getTotal(), $preview->getDeletable(), $preview->getProtected()]);
        self::assertSame(['native', 'used_in_order_history', 'used_in_order_history', 'used_in_order_history', 'has_reviews'], array_values(array_map(static fn ($entry) => $entry->reason, array_filter($preview->entries, static fn ($entry) => 'protected' === $entry->status))));

        $id = $deletable->getId();
        self::assertSame(1, $this->manager->applyReferences('products', $refs)->getDeleted());
        $this->em->clear();
        self::assertNull($this->em->getRepository(Product::class)->find($id));
    }

    public function testDuplicateInputIsNeverDeletedTwice(): void
    {
        $user = $this->user('DUPLICATE', DataOrigin::Imported);
        $this->em->flush();
        $file = sys_get_temp_dir().'/shopwho-reset-'.$this->suffix.'.json';
        file_put_contents($file, json_encode(['users' => [['externalRef' => $user->getExternalRef()], ['externalRef' => $user->getExternalRef()]]], JSON_THROW_ON_ERROR));

        $result = $this->manager->applyFile('users', $file);
        self::assertSame([2, 1, 1], [$result->getTotal(), $result->getDeleted(), $result->getSkipped()]);
    }

    public function testInfrastructureFailureRollsBackTheWholeDeletableBatch(): void
    {
        $first = $this->user('ROLLBACK-A', DataOrigin::Imported);
        $second = $this->user('ROLLBACK-B', DataOrigin::Imported);
        $this->em->flush();
        $ids = [$first->getId(), $second->getId()];
        $listener = new class {
            public function onFlush(): void { throw new \RuntimeException('FICTION infrastructure failure'); }
        };
        $events = $this->em->getEventManager();
        $events->addEventListener([Events::onFlush], $listener);

        try {
            $this->manager->applyReferences('users', [$first->getExternalRef(), $second->getExternalRef()]);
            self::fail('The infrastructure exception must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('FICTION infrastructure failure', $exception->getMessage());
        } finally {
            $events->removeEventListener([Events::onFlush], $listener);
        }

        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM app_user WHERE id IN (?, ?)', $ids));
    }

    public function testApplyRevalidatesProtectionAfterAnEarlierPreview(): void
    {
        $user = $this->user('CONCURRENT', DataOrigin::Imported);
        $this->em->flush();
        self::assertSame(1, $this->manager->previewReferences('users', [$user->getExternalRef()])->getDeletable());
        $this->em->persist(new Order($user, 'CONCURRENT-'.$this->suffix, 100));
        $this->em->flush();

        $result = $this->manager->applyReferences('users', [$user->getExternalRef()]);
        self::assertSame([0, 0, 1], [$result->getDeletable(), $result->getDeleted(), $result->getProtected()]);
        self::assertSame('has_orders', $result->entries[0]->reason);
        self::assertNotNull($this->em->getRepository(User::class)->find($user->getId()));
    }

    private function user(string $name, DataOrigin $origin): User
    {
        $user = (new User())->setEmail(strtolower($name).'-'.strtolower($this->suffix).'@example.test')->setPassword('unused')
            ->setExternalRef('USR-FICTION-'.$name.'-'.$this->suffix)->setDataOrigin($origin);
        $this->em->persist($user);

        return $user;
    }

    private function product(string $name, DataOrigin $origin): Product
    {
        if (null === $this->category) {
            $this->category = $this->em->getRepository(Category::class)->findOneBy([]);
            if (null === $this->category) {
                $this->category = (new Category())->setName('FICTION '.$this->suffix)->setSlug('fiction-'.strtolower($this->suffix));
                $this->em->persist($this->category);
            }
        }
        $product = (new Product())->setExternalRef('PROD-FICTION-'.$name.'-'.$this->suffix)->setDataOrigin($origin)
            ->setName('Produit FICTION '.$name)->setSlug('fiction-'.strtolower($name).'-'.strtolower($this->suffix))
            ->setDescription('Donnée exclusivement synthétique.')->setPriceCents(100)->setStock(1)->setCategory($this->category);
        $this->em->persist($product);

        return $product;
    }
}
