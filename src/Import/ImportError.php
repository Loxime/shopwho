<?php
namespace App\Import;
final readonly class ImportError implements \JsonSerializable { public function __construct(public int $record,public ?string $externalRef,public string $message){} public function jsonSerialize():array{return ['record'=>$this->record,'externalRef'=>$this->externalRef,'message'=>$this->message];}}
