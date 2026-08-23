<?php
namespace App\Import\DTO;
use Symfony\Component\Validator\Constraints as Assert;
final readonly class UserImportDto {
    public function __construct(public int $record, #[Assert\NotBlank, Assert\Length(max: 180)] public string $externalRef, #[Assert\NotBlank, Assert\Email, Assert\Length(max: 180)] public string $email, #[Assert\Length(max: 100)] public ?string $firstName, #[Assert\Length(max: 100)] public ?string $lastName, public ?\DateTimeImmutable $createdAt) {}
}
