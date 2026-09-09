<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'article')]
#[UniqueEntity(
    fields: ['slug'],
    message: 'Ce slug est déjà utilisé.'
)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(length: 200, unique: true)]
    #[Assert\Length(max: 200)]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $excerpt = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $content = '';

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private ?string $coverImageUrl = null;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);
        $this->touch();

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = trim($slug);
        $this->touch();

        return $this;
    }

    public function getExcerpt(): string
    {
        return $this->excerpt;
    }

    public function setExcerpt(string $excerpt): self
    {
        $this->excerpt = trim($excerpt);
        $this->touch();

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = trim($content);
        $this->touch();

        return $this;
    }

    public function getCoverImageUrl(): ?string
    {
        return $this->coverImageUrl;
    }

    public function setCoverImageUrl(
        ?string $coverImageUrl
    ): self {
        $coverImageUrl = $coverImageUrl === null
            ? null
            : trim($coverImageUrl);

        $this->coverImageUrl =
            $coverImageUrl === ''
                ? null
                : $coverImageUrl;

        $this->touch();

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(
        bool $isPublished
    ): self {
        $this->isPublished = $isPublished;
        $this->touch();

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(
        ?\DateTimeImmutable $publishedAt
    ): self {
        $this->publishedAt = $publishedAt;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isVisibleAt(
        ?\DateTimeImmutable $now = null
    ): bool {
        if (!$this->isPublished) {
            return false;
        }

        $now ??= new \DateTimeImmutable();

        return $this->publishedAt === null
            || $this->publishedAt <= $now;
    }

    public function __toString(): string
    {
        return $this->title;
    }

    private function touch(): void
    {
        $this->updatedAt =
            new \DateTimeImmutable();
    }
}
