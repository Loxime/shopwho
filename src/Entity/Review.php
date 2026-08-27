<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'product_review')]
#[ORM\UniqueConstraint(name: 'uniq_product_review_user_product', columns: ['user_id', 'product_id'])]
#[ORM\Index(name: 'idx_product_review_created_at', columns: ['created_at'])]
#[UniqueEntity(fields: ['user', 'product'], message: 'Vous avez déjà publié un avis sur ce produit.')]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private User $user;

    #[ORM\ManyToOne(inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Product $product;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 5)]
    private int $rating;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 3000)]
    private ?string $comment = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, Product $product, int $rating, ?\DateTimeImmutable $createdAt = null)
    {
        $this->user = $user;
        $this->product = $product;
        $this->rating = $rating;
        $now = $createdAt ?? new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }
    public function getExternalRef(): ?string { return $this->externalRef; }
    public function setExternalRef(?string $externalRef): self { $this->externalRef = $this->normalize($externalRef); return $this; }
    public function getUser(): User { return $this->user; }
    public function getProduct(): Product { return $this->product; }
    public function getRating(): int { return $this->rating; }
    public function setRating(int $rating): self { $this->rating = $rating; return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): self { $this->comment = $this->normalize($comment); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    private function normalize(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $now = new \DateTimeImmutable();
        $this->updatedAt = $now > $this->updatedAt ? $now : $this->updatedAt->modify('+1 second');
    }
}
