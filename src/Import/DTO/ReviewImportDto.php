<?php
namespace App\Import\DTO;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class ReviewImportDto {
    public function __construct(public int $record, #[Assert\NotBlank] public string $externalRef, #[Assert\NotBlank] public string $userExternalRef, #[Assert\NotBlank] public string $productExternalRef, #[Assert\Range(min:1,max:5)] public int $rating, #[Assert\Length(max:3000)] public ?string $comment, public ?\DateTimeImmutable $createdAt) {}
}
