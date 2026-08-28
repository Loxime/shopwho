<?php

namespace App\Import\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CategoryImportDto
{
    public function __construct(
        public int $record,

        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public string $externalRef,

        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Length(max: 140)]
        #[Assert\Regex(
            pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            message: 'Le slug doit contenir uniquement des lettres minuscules, chiffres et tirets.'
        )]
        public string $slug,

        #[Assert\Length(max: 100)]
        public ?string $icon,

        public bool $isFeatured,

        public bool $showInNavigation,

        #[Assert\PositiveOrZero]
        public int $navigationPosition,
    ) {
    }
}
