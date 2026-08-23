<?php

namespace App\Tests;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Kernel;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class OrderServiceTest extends KernelTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testCreatesOrderAndKeepsProductSnapshotsStable(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));
        $category = (new Category())->setName('Catégorie '.$suffix)->setSlug('category-'.$suffix);
        $product = (new Product())
            ->setName('Produit original')
            ->setSlug('produit-'.$suffix)
            ->setDescription('Fixture commande')
            ->setPriceCents(1299)
            ->setStock(10)
            ->setCategory($category);
        $user = (new User())->setEmail("order-service-$suffix@shopwho.local")->setPassword('not-used');
        $em->persist($category);
        $em->persist($product);
        $em->persist($user);
        $em->flush();

        /** @var OrderService $service */
        $service = static::getContainer()->get(OrderService::class);
        $order = $service->createFromCart($user, [
            ['product' => $product, 'quantity' => 2, 'lineTotal' => 25.98],
        ], 2598);

        self::assertSame($user, $order->getUser());
        self::assertSame(2598, $order->getTotalCents());
        self::assertCount(1, $order->getItems());
        self::assertSame(2, $order->getItemCount());
        $item = $order->getItems()->first();
        self::assertSame('Produit original', $item->getProductNameSnapshot());
        self::assertSame('produit-'.$suffix, $item->getProductSlugSnapshot());
        self::assertSame(1299, $item->getUnitPriceCents());
        self::assertSame(2, $item->getQuantity());
        self::assertSame(2598, $item->getLineTotalCents());

        $product->setName('Produit renommé')->setSlug('renamed-'.$suffix)->setPriceCents(9999);
        $em->flush();
        $orderId = $order->getId();
        $em->clear();

        $persisted = $em->getRepository(Order::class)->find($orderId);
        $persistedItem = $persisted->getItems()->first();
        self::assertSame('Produit original', $persistedItem->getProductNameSnapshot());
        self::assertSame('produit-'.$suffix, $persistedItem->getProductSlugSnapshot());
        self::assertSame(1299, $persistedItem->getUnitPriceCents());
    }
}
