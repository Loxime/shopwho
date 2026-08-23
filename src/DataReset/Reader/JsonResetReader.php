<?php

namespace App\DataReset\Reader;

use App\DataReset\ResetType;
use App\Import\Exception\ImportException;

final readonly class JsonResetReader implements ResetReaderInterface
{
    public function __construct(private ReferenceNormalizer $normalizer)
    {
    }

    public function supports(string $extension): bool { return 'json' === strtolower($extension); }

    public function read(ResetType $type, string $file): ResetPayload
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new ImportException(sprintf('File "%s" is not readable.', $file));
        }
        try {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ImportException('Invalid JSON: '.$exception->getMessage());
        }
        if (!is_array($data) || !isset($data[$type->value]) || !is_array($data[$type->value])) {
            throw new ImportException(sprintf('JSON root key "%s" must contain an array.', $type->value));
        }

        $rows = [];
        foreach (array_values($data[$type->value]) as $index => $row) {
            $record = $index + 1;
            if (!is_array($row)) {
                $rows[] = ['value' => null, 'record' => $record];
                continue;
            }
            $rows[] = ['value' => $row['externalRef'] ?? null, 'record' => $record];
        }

        return $this->normalizer->normalize($rows);
    }
}
