<?php

namespace App\DataReset\Reader;

use App\DataReset\ResetEntry;

final readonly class ResetPayload
{
    /** @param list<string> $references @param list<ResetEntry> $issues */
    public function __construct(public array $references, public array $issues)
    {
    }
}
