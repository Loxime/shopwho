<?php

namespace App\DataReset;

final readonly class ResetResult implements \JsonSerializable
{
    /** @param list<ResetEntry> $entries */
    public function __construct(public array $entries)
    {
    }

    public function getTotal(): int { return count($this->entries); }
    public function getDeletable(): int { return $this->count('deletable') + $this->count('deleted'); }
    public function getDeleted(): int { return $this->count('deleted'); }
    public function getProtected(): int { return $this->count('protected'); }
    public function getNotFound(): int { return $this->count('not_found'); }
    public function getFailed(): int { return $this->count('failed'); }
    public function getSkipped(): int { return $this->count('duplicate'); }

    public function jsonSerialize(): array
    {
        return [
            'total' => $this->getTotal(),
            'deletable' => $this->getDeletable(),
            'deleted' => $this->getDeleted(),
            'protected' => $this->getProtected(),
            'notFound' => $this->getNotFound(),
            'failed' => $this->getFailed(),
            'skipped' => $this->getSkipped(),
            'entries' => $this->entries,
        ];
    }

    private function count(string $status): int
    {
        return count(array_filter($this->entries, static fn (ResetEntry $entry): bool => $entry->status === $status));
    }
}
