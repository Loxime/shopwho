<?php

namespace App\Entity;

use App\Enum\DataOrigin;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\Column(length: 20, enumType: DataOrigin::class)]
    private DataOrigin $dataOrigin = DataOrigin::Native;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 190, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $description = '';

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $priceCents = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $stock = 0;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Category $category = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    private ?string $imageUrl = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Review::class)]
    private Collection $reviews;

    /** @var Collection<int, Favorite> */
    #[ORM\OneToMany(
        mappedBy: 'product',
        targetEntity: Favorite::class,
        orphanRemoval: true
    )]
    private Collection $favorites;
    
    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->reviews = new ArrayCollection();
        $this->favorites = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getExternalRef(): ?string { return $this->externalRef; }
    public function setExternalRef(?string $externalRef): self { $this->externalRef = null === $externalRef || '' === trim($externalRef) ? null : trim($externalRef); return $this; }
    public function getDataOrigin(): DataOrigin { return $this->dataOrigin; }
    public function setDataOrigin(DataOrigin $dataOrigin): self { $this->dataOrigin = $dataOrigin; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; $this->touch(); return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; $this->touch(); return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; $this->touch(); return $this; }
    public function getPriceCents(): int { return $this->priceCents; }
    public function setPriceCents(int $priceCents): self { $this->priceCents = $priceCents; $this->touch(); return $this; }
    public function getPrice(): float { return $this->priceCents / 100; }
    public function setPrice(float $price): self { $this->priceCents = (int) round($price * 100); $this->touch(); return $this; }
    public function getStock(): int { return $this->stock; }
    public function setStock(int $stock): self { $this->stock = $stock; $this->touch(); return $this; }
    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(Category $category): self { $this->category = $category; $this->touch(); return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): self { $this->imageUrl = $imageUrl ?: null; $this->touch(); return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; $this->touch(); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    /** @return Collection<int, Review> */
    public function getReviews(): Collection { return $this->reviews; }
    
    /**
     * @return Collection<int, Favorite>
     */
    public function getFavorites(): Collection
    {
        return $this->favorites;
    }
    
    public function hasReviews(): bool { return !$this->reviews->isEmpty(); }

    private function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
