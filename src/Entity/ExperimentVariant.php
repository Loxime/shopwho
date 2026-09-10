<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'ab_experiment_variant')]
#[ORM\UniqueConstraint(
    name: 'uniq_ab_experiment_variant_key',
    columns: [
        'experiment_id',
        'variant_key',
    ]
)]
class ExperimentVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(
        inversedBy: 'variants'
    )]
    #[ORM\JoinColumn(
        name: 'experiment_id',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private ?Experiment $experiment = null;

    #[ORM\Column(
        name: 'variant_key',
        length: 80
    )]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9][a-z0-9._-]*$/',
        message: 'La clé doit utiliser des lettres minuscules, chiffres, points, tirets ou underscores.'
    )]
    private string $key = '';

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $name = '';

    #[ORM\Column]
    #[Assert\Positive]
    private int $weight = 1;

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

    public function getExperiment(): ?Experiment
    {
        return $this->experiment;
    }

    public function setExperiment(
        ?Experiment $experiment
    ): self {
        $this->experiment = $experiment;
        $this->touch();

        return $this;
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

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): self
    {
        if ($weight <= 0) {
            throw new \OutOfRangeException(
                'Le poids d’une variante doit être strictement positif.'
            );
        }

        $this->weight = $weight;
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
