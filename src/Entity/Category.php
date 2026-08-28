<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use App\Enum\DataOrigin;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'category')]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug est déjà utilisé.')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\Column(length: 20, enumType: DataOrigin::class)]
    private DataOrigin $dataOrigin = DataOrigin::Native;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 140, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Le slug doit contenir uniquement des lettres minuscules, chiffres et tirets.')]
    private string $slug = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private bool $showInNavigation = true;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $navigationPosition = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Product> */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Product::class)]
    private Collection $products;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getExternalRef(): ?string
    {
        return $this->externalRef;
    }

    public function setExternalRef(?string $externalRef): self
    {
        $this->externalRef = null === $externalRef
            || '' === trim($externalRef)
            ? null
            : trim($externalRef);

        return $this;
    }

    public function getDataOrigin(): DataOrigin
    {
        return $this->dataOrigin;
    }

    public function setDataOrigin(DataOrigin $dataOrigin): self
    {
        $this->dataOrigin = $dataOrigin;

        return $this;
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = trim($name); $this->touch(); return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = strtolower(trim($slug)); $this->touch(); return $this; }
    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(?string $icon): self { $this->icon = $icon ? trim($icon) : null; $this->touch(); return $this; }
    public function isFeatured(): bool { return $this->isFeatured; }
    public function setIsFeatured(bool $isFeatured): self { $this->isFeatured = $isFeatured; $this->touch(); return $this; }
    public function isShowInNavigation(): bool { return $this->showInNavigation; }
    public function setShowInNavigation(bool $showInNavigation): self { $this->showInNavigation = $showInNavigation; $this->touch(); return $this; }
    public function getNavigationPosition(): int { return $this->navigationPosition; }
    public function setNavigationPosition(int $navigationPosition): self { $this->navigationPosition = $navigationPosition; $this->touch(); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection { return $this->products; }
    public function hasProducts(): bool { return !$this->products->isEmpty(); }
    public function __toString(): string { return $this->name; }

    private function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
