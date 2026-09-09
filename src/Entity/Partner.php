<?php

namespace App\Entity;

use App\Repository\PartnerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PartnerRepository::class)]
#[ORM\Table(name: 'partner')]
class Partner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1500)]
    private ?string $description = null;

    #[ORM\Column(length: 1000)]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private string $websiteUrl = '';

    #[ORM\Column(length: 1000, nullable: true)]
    #[Assert\Url]
    #[Assert\Length(max: 1000)]
    private ?string $logoUrl = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $priority = 0;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(
        string $name
    ): self {
        $this->name = trim($name);
        $this->touch();

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(
        ?string $description
    ): self {
        $description = $description === null
            ? null
            : trim($description);

        $this->description =
            $description === ''
                ? null
                : $description;

        $this->touch();

        return $this;
    }

    public function getWebsiteUrl(): string
    {
        return $this->websiteUrl;
    }

    public function setWebsiteUrl(
        string $websiteUrl
    ): self {
        $this->websiteUrl =
            trim($websiteUrl);

        $this->touch();

        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(
        ?string $logoUrl
    ): self {
        $logoUrl = $logoUrl === null
            ? null
            : trim($logoUrl);

        $this->logoUrl =
            $logoUrl === ''
                ? null
                : $logoUrl;

        $this->touch();

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(
        bool $isActive
    ): self {
        $this->isActive = $isActive;
        $this->touch();

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(
        int $priority
    ): self {
        $this->priority = max(
            0,
            $priority
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

    public function __toString(): string
    {
        return $this->name;
    }

    private function touch(): void
    {
        $this->updatedAt =
            new \DateTimeImmutable();
    }
}
