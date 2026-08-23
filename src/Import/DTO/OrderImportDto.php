<?php
namespace App\Import\DTO;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class OrderImportDto {
    /** @param list<OrderItemImportDto> $items */
    public function __construct(public int $record, #[Assert\NotBlank, Assert\Length(max: 180)] public string $externalRef, #[Assert\NotBlank, Assert\Length(max: 180)] public string $userExternalRef, #[Assert\Choice(choices: ['completed','cancelled','refunded'])] public string $status, public ?\DateTimeImmutable $orderedAt, #[Assert\PositiveOrZero] public ?int $totalCents, #[Assert\Count(min: 1), Assert\Valid] public array $items = []) {}
    /** @param list<OrderItemImportDto> $items */ public function withItems(array $items): self { return new self($this->record,$this->externalRef,$this->userExternalRef,$this->status,$this->orderedAt,$this->totalCents,$items); }
}
