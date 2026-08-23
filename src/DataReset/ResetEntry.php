<?php

namespace App\DataReset;

final readonly class ResetEntry implements \JsonSerializable
{
    public function __construct(
        public ?string $externalRef,
        public string $status,
        public ?string $reason = null,
        public ?int $record = null,
        public ?int $relatedCount = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
