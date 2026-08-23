<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param list<array{product: Product, quantity: int, lineTotal: float}> $lines
     */
    public function createFromCart(User $user, array $lines, int $totalCents): Order
    {
        return $this->entityManager->wrapInTransaction(function () use ($user, $lines, $totalCents): Order {
            $order = $this->persistFromCart($user, $lines, $totalCents);
            $this->entityManager->flush();

            return $order;
        });
    }

    /**
     * @param list<array{product: Product, quantity: int, lineTotal: float}> $lines
     */
    public function persistFromCart(User $user, array $lines, int $totalCents): Order
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('Une commande doit contenir au moins une ligne.');
        }

        $calculatedTotal = 0;
        $order = new Order($user, $this->generateReference(), $totalCents);

        foreach ($lines as $line) {
            $item = OrderItem::fromProduct($order, $line['product'], $line['quantity']);
            $calculatedTotal += $item->getLineTotalCents();
        }

        if ($calculatedTotal !== $totalCents) {
            throw new \InvalidArgumentException('Le total de commande ne correspond pas aux lignes du panier.');
        }

        $this->entityManager->persist($order);

        return $order;
    }

    private function generateReference(): string
    {
        return sprintf('SW-%s-%s', (new \DateTimeImmutable())->format('Ymd'), strtoupper(bin2hex(random_bytes(6))));
    }
}
