<?php

namespace App\DataReset\Reader;

use App\DataReset\ResetEntry;

final class ReferenceNormalizer
{
    /** @param iterable<array{value:mixed,record:int}> $rows */
    public function normalize(iterable $rows): ResetPayload
    {
        $references = [];
        $issues = [];
        $seen = [];

        foreach ($rows as $row) {
            if (!is_scalar($row['value']) || '' === ($reference = trim((string) $row['value']))) {
                $issues[] = new ResetEntry(null, 'failed', 'empty_external_ref', $row['record']);
                continue;
            }
            if (isset($seen[$reference])) {
                $issues[] = new ResetEntry($reference, 'duplicate', 'duplicate', $row['record']);
                continue;
            }
            $seen[$reference] = true;
            $references[] = $reference;
        }

        return new ResetPayload($references, $issues);
    }
}
