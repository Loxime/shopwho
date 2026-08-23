<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'order_item')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product;

    #[ORM\Column(nullable: true)]
    private ?int $productIdSnapshot;

    #[ORM\Column(length: 180)]
    private string $productNameSnapshot;

    #[ORM\Column(length: 190)]
    private string $productSlugSnapshot;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $unitPriceCents;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $lineTotalCents;

    public function __construct(Order $order, Product $product, int $quantity)
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('La quantité doit être strictement positive.');
        }

        $this->order = $order;
        $this->product = $product;
        $this->productIdSnapshot = $product->getId();
        $this->productNameSnapshot = $product->getName();
        $this->productSlugSnapshot = $product->getSlug();
        $this->quantity = $quantity;
        $this->unitPriceCents = $product->getPriceCents();
        $this->lineTotalCents = $this->unitPriceCents * $quantity;
        $order->addItem($this);
    }

    public function getId(): ?int { return $this->id; }
    public function getOrder(): Order { return $this->order; }
    public function getProduct(): ?Product { return $this->product; }
    public function getProductIdSnapshot(): ?int { return $this->productIdSnapshot; }
    public function getProductNameSnapshot(): string { return $this->productNameSnapshot; }
    public function getProductSlugSnapshot(): string { return $this->productSlugSnapshot; }
    public function getQuantity(): int { return $this->quantity; }
    public function getUnitPriceCents(): int { return $this->unitPriceCents; }
    public function getLineTotalCents(): int { return $this->lineTotalCents; }
}
