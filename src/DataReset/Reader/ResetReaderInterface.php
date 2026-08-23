<?php

namespace App\DataReset\Reader;

use App\DataReset\ResetType;

interface ResetReaderInterface
{
    public function supports(string $extension): bool;
    public function read(ResetType $type, string $file): ResetPayload;
}
