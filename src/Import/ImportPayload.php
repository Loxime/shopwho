<?php
namespace App\Import;
final readonly class ImportPayload { /** @param list<object> $records */ public function __construct(public string $type, public array $records) {} }
