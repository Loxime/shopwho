<?php

namespace App\DataReset\Policy;

final readonly class ResetDecision
{
    public function __construct(public bool $deletable, public ?string $reason = null, public ?int $relatedCount = null)
    {
    }
}
