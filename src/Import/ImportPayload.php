<?php
namespace App\Import;
final readonly class ImportPayload
{
    /** @param list<object> $records @param list<ImportError> $errors */
    public function __construct(public string $type, public array $records, public array $errors = []) {}
}
