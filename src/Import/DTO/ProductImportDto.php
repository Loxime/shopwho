<?php
namespace App\Import\DTO;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class ProductImportDto {
    public function __construct(public int $record, #[Assert\NotBlank, Assert\Length(max: 180)] public string $externalRef, #[Assert\NotBlank, Assert\Length(max: 180)] public string $name, #[Assert\NotBlank, Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'), Assert\Length(max: 190)] public string $slug, #[Assert\NotBlank] public string $description, #[Assert\PositiveOrZero] public int $priceCents, #[Assert\PositiveOrZero] public int $stock, #[Assert\NotBlank, Assert\Length(max: 140)] public string $categorySlug, #[Assert\Url, Assert\Length(max: 500)] public ?string $imageUrl, public bool $isActive) {}
}
