<?php
namespace App\Import\Importer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
#[AutoconfigureTag]
interface DataImporterInterface {public function supports(string $type):bool; public function import(object $dto):ImportOutcome;}
