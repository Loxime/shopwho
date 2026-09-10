<?php

namespace App\Entity;

use App\Enum\ExperimentStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'ab_experiment')]
class Experiment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(
        name: 'experiment_key',
        length: 120,
        unique: true
    )]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9][a-z0-9._-]*$/',
        message: 'La clé doit utiliser des lettres minuscules, chiffres, points, tirets ou underscores.'
    )]
    private string $key = '';

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $name = '';

    #[ORM\Column(
        length: 20,
        enumType: ExperimentStatus::class
    )]
    private ExperimentStatus $status =
        ExperimentStatus::Draft;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 0, max: 100)]
    private int $trafficPercentage = 100;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    /**
     * @var Collection<int, ExperimentVariant>
     */
    #[ORM\OneToMany(
        mappedBy: 'experiment',
        targetEntity: ExperimentVariant::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $variants;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->variants = new ArrayCollection();

        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = strtolower(trim($key));
        $this->touch();

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);
        $this->touch();

        return $this;
    }

    public function getStatus(): ExperimentStatus
    {
        return $this->status;
    }

    public function setStatus(
        ExperimentStatus $status
    ): self {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getTrafficPercentage(): int
    {
        return $this->trafficPercentage;
    }

    public function setTrafficPercentage(
        int $trafficPercentage
    ): self {
        if (
            $trafficPercentage < 0
            || $trafficPercentage > 100
        ) {
            throw new \OutOfRangeException(
                'Le pourcentage de trafic doit être compris entre 0 et 100.'
            );
        }

        $this->trafficPercentage =
            $trafficPercentage;

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

    /**
     * @return Collection<int, ExperimentVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(
        ExperimentVariant $variant
    ): self {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setExperiment($this);
            $this->touch();
        }

        return $this;
    }

    public function removeVariant(
        ExperimentVariant $variant
    ): self {
        if ($this->variants->removeElement($variant)) {
            if ($variant->getExperiment() === $this) {
                $variant->setExperiment(null);
            }

            $this->touch();
        }

        return $this;
    }

    public function isAssignable(
        ?\DateTimeImmutable $now = null
    ): bool {
        if ($this->status !== ExperimentStatus::Running) {
            return false;
        }

        if ($this->trafficPercentage <= 0) {
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
