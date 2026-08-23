<?php
namespace App\Import\DTO;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class OrderItemImportDto {
    public function __construct(public int $record, #[Assert\NotBlank] public string $orderExternalRef, public ?string $productExternalRef, #[Assert\NotBlank] public string $productNameSnapshot, #[Assert\NotBlank] public string $productSlugSnapshot, #[Assert\Positive] public int $quantity, #[Assert\PositiveOrZero] public int $unitPriceCents) {}
}
