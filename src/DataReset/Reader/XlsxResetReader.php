<?php

namespace App\DataReset\Reader;

use App\DataReset\ResetType;
use App\Import\Exception\ImportException;
use OpenSpout\Reader\XLSX\Reader;

final readonly class XlsxResetReader implements ResetReaderInterface
{
    public function __construct(private ReferenceNormalizer $normalizer)
    {
    }

    public function supports(string $extension): bool { return 'xlsx' === strtolower($extension); }

    public function read(ResetType $type, string $file): ResetPayload
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new ImportException(sprintf('File "%s" is not readable.', $file));
        }
        $reader = new Reader();
        try {
            $reader->open($file);
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() === $type->value) {
                    return $this->readSheet($sheet, $type);
                }
            }
        } catch (ImportException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ImportException('Invalid XLSX: '.$exception->getMessage());
        } finally {
            $reader->close();
        }
        throw new ImportException(sprintf('Required sheet "%s" is missing.', $type->value));
    }

    private function readSheet(object $sheet, ResetType $type): ResetPayload
    {
        $headerIndex = null;
        $rows = [];
        $line = 0;
        foreach ($sheet->getRowIterator() as $row) {
            ++$line;
            $values = $row->toArray();
            if (null === $headerIndex) {
                if ([] === array_filter($values, static fn ($value): bool => null !== $value && '' !== trim((string) $value))) {
                    continue;
                }
                $headers = array_map(static fn ($value): string => trim((string) $value), $values);
                $headerIndex = array_search('externalRef', $headers, true);
                if (false === $headerIndex) {
                    throw new ImportException(sprintf('Sheet "%s": missing header: externalRef.', $type->value));
                }
                continue;
            }
            if ([] === array_filter($values, static fn ($value): bool => null !== $value && '' !== trim((string) $value))) {
                continue;
            }
            $rows[] = ['value' => $values[$headerIndex] ?? null, 'record' => $line];
        }
        if (null === $headerIndex) {
            throw new ImportException(sprintf('Sheet "%s" is empty.', $type->value));
        }

        return $this->normalizer->normalize($rows);
    }
}
