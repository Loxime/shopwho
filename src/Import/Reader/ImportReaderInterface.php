<?php
namespace App\Import\Reader;
use App\Import\ImportPayload;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
#[AutoconfigureTag]
interface ImportReaderInterface { public function supports(string $extension): bool; public function read(string $type,string $file): ImportPayload; }
