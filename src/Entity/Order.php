<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'customer_order')]
#[ORM\Index(name: 'idx_customer_order_ordered_at', columns: ['ordered_at'])]
class Order
{
    public const STATUS_SIMULATED_COMPLETED = 'simulated_completed';
    /** @deprecated use STATUS_SIMULATED_COMPLETED */
    public const STATUS_COMPLETED = self::STATUS_SIMULATED_COMPLETED;
    public const STATUS_IMPORTED_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const IMPORT_STATUSES = [self::STATUS_IMPORTED_COMPLETED, self::STATUS_CANCELLED, self::STATUS_REFUNDED];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    private string $reference;

    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 40)]
    private string $status = self::STATUS_SIMULATED_COMPLETED;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $totalCents;

    #[ORM\Column]
    private \DateTimeImmutable $orderedAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(User $user, string $reference, int $totalCents, ?\DateTimeImmutable $orderedAt = null)
    {
        if ($totalCents < 0) {
            throw new \InvalidArgumentException('Le total de commande ne peut pas être négatif.');
        }

        $this->user = $user;
        $this->reference = $reference;
        $this->totalCents = $totalCents;
        $this->orderedAt = $orderedAt ?? new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getExternalRef(): ?string { return $this->externalRef; }
    public function setExternalRef(?string $externalRef): self { $this->externalRef = $externalRef === null || trim($externalRef) === '' ? null : trim($externalRef); return $this; }
    public function getUser(): User { return $this->user; }
    public function getStatus(): string { return $this->status; }
    public function getStatusLabel(): string { return match ($this->status) { self::STATUS_SIMULATED_COMPLETED => 'Terminée (simulation)', self::STATUS_IMPORTED_COMPLETED => 'Terminée', self::STATUS_CANCELLED => 'Annulée', self::STATUS_REFUNDED => 'Remboursée', default => $this->status }; }
    public function getTotalCents(): int { return $this->totalCents; }
    public function getOrderedAt(): \DateTimeImmutable { return $this->orderedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public static function import(User $user, string $externalRef, string $status, int $totalCents, ?\DateTimeImmutable $orderedAt = null): self
    {
        if (!in_array($status, self::IMPORT_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown imported order status "%s".', $status));
        }
        $order = new self($user, 'IMP-'.strtoupper(bin2hex(random_bytes(8))), $totalCents, $orderedAt);
        $order->externalRef = trim($externalRef);
        $order->status = $status;
        return $order;
    }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection { return $this->items; }

    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }

        return $this;
    }

    public function getItemCount(): int
    {
        return array_sum($this->items->map(static fn (OrderItem $item): int => $item->getQuantity())->toArray());
    }
}
