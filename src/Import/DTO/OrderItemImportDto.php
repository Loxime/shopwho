<?php
namespace App\Import\DTO;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class OrderItemImportDto {
    public function __construct(public int $record, #[Assert\NotBlank, Assert\Length(max: 180)] public string $orderExternalRef, #[Assert\Length(max: 180)] public ?string $productExternalRef, #[Assert\NotBlank, Assert\Length(max: 180)] public string $productNameSnapshot, #[Assert\NotBlank, Assert\Length(max: 190)] public string $productSlugSnapshot, #[Assert\Positive] public int $quantity, #[Assert\PositiveOrZero] public int $unitPriceCents) {}
}
