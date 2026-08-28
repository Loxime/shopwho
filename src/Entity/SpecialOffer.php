<?php

namespace App\Entity;

use App\Enum\SpecialOfferPlacement;
use App\Repository\SpecialOfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SpecialOfferRepository::class)]
#[ORM\Table(name: 'special_offer')]
#[ORM\Index(
    name: 'idx_special_offer_active_dates',
    columns: [
        'is_active',
        'starts_at',
        'ends_at',
    ]
)]
#[ORM\Index(
    name: 'idx_special_offer_placement_priority',
    columns: [
        'placement',
        'priority',
    ]
)]
class SpecialOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 1000)]
    private string $content = '';

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $ctaLabel = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Assert\Regex(
        pattern: '#^(?:/[^\\s]*|https?://[^\\s]+)$#',
        message: 'La redirection doit être un chemin interne ou une URL HTTP(S).'
    )]
    private ?string $targetUrl = null;

    #[ORM\Column(
        length: 20,
        enumType: SpecialOfferPlacement::class
    )]
    private SpecialOfferPlacement $placement =
        SpecialOfferPlacement::Homepage;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(
        nullable: true,
        onDelete: 'SET NULL'
    )]
    private ?Category $targetCategory = null;

    #[ORM\Column(length: 7)]
    #[Assert\Regex(
        pattern: '/^#[0-9A-Fa-f]{6}$/',
        message: 'La couleur doit être au format #RRGGBB.'
    )]
    private string $backgroundColor = '#272785';

    #[ORM\Column(length: 7)]
    #[Assert\Regex(
        pattern: '/^#[0-9A-Fa-f]{6}$/',
        message: 'La couleur doit être au format #RRGGBB.'
    )]
    private string $textColor = '#FFFFFF';

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $priority = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $experimentKey = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $experimentVariant = null;

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

    public function getCtaLabel(): ?string
    {
        return $this->ctaLabel;
    }

    public function setCtaLabel(?string $ctaLabel): self
    {
        $this->ctaLabel = $this->normalizeNullable(
            $ctaLabel
        );

        $this->touch();

        return $this;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): self
    {
        $this->targetUrl = $this->normalizeNullable(
            $targetUrl
        );

        $this->touch();

        return $this;
    }

    public function getPlacement(): SpecialOfferPlacement
    {
        return $this->placement;
    }

    public function setPlacement(
        SpecialOfferPlacement $placement
    ): self {
        $this->placement = $placement;
        $this->touch();

        return $this;
    }

    public function getTargetCategory(): ?Category
    {
        return $this->targetCategory;
    }

    public function setTargetCategory(
        ?Category $targetCategory
    ): self {
        $this->targetCategory = $targetCategory;
        $this->touch();

        return $this;
    }

    public function getBackgroundColor(): string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(
        string $backgroundColor
    ): self {
        $this->backgroundColor = strtoupper(
            trim($backgroundColor)
        );

        $this->touch();

        return $this;
    }

    public function getTextColor(): string
    {
        return $this->textColor;
    }

    public function setTextColor(
        string $textColor
    ): self {
        $this->textColor = strtoupper(
            trim($textColor)
        );

        $this->touch();

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        $this->touch();

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(
        ?\DateTimeImmutable $startsAt
    ): self {
        $this->startsAt = $startsAt;
        $this->touch();

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(
        ?\DateTimeImmutable $endsAt
    ): self {
        $this->endsAt = $endsAt;
        $this->touch();

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        $this->touch();

        return $this;
    }

    public function getExperimentKey(): ?string
    {
        return $this->experimentKey;
    }

    public function setExperimentKey(
        ?string $experimentKey
    ): self {
        $this->experimentKey =
            $this->normalizeNullable(
                $experimentKey
            );

        $this->touch();

        return $this;
    }

    public function getExperimentVariant(): ?string
    {
        return $this->experimentVariant;
    }

    public function setExperimentVariant(
        ?string $experimentVariant
    ): self {
        $this->experimentVariant =
            $this->normalizeNullable(
                $experimentVariant
            );

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

    public function isCurrentlyActive(
        ?\DateTimeImmutable $now = null
    ): bool {
        if (!$this->isActive) {
            return false;
        }

        $now ??= new \DateTimeImmutable();

        if (
            $this->startsAt !== null
            && $this->startsAt > $now
        ) {
            return false;
        }

        if (
            $this->endsAt !== null
            && $this->endsAt < $now
        ) {
            return false;
        }

        return true;
    }

    public function __toString(): string
    {
        return $this->title;
    }

    private function normalizeNullable(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    private function touch(): void
    {
        $this->updatedAt =
            new \DateTimeImmutable();
    }
}
