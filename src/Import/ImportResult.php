<?php
namespace App\Import;
final class ImportResult implements \JsonSerializable {
 private int $total=0,$created=0,$updated=0,$skipped=0,$failed=0; /** @var list<ImportError> */ private array $errors=[];
 public function countTotal():void{$this->total++;} public function created():void{$this->created++;} public function updated():void{$this->updated++;} public function skipped():void{$this->skipped++;}
 public function failed(int $record,?string $externalRef,string $message):void{$this->failed++;$this->errors[]=new ImportError($record,$externalRef,$message);}
 public function getTotal():int{return $this->total;} public function getCreated():int{return $this->created;} public function getUpdated():int{return $this->updated;} public function getSkipped():int{return $this->skipped;} public function getFailed():int{return $this->failed;} /** @return list<ImportError> */ public function getErrors():array{return $this->errors;}
 public function jsonSerialize():array{return ['total'=>$this->total,'created'=>$this->created,'updated'=>$this->updated,'skipped'=>$this->skipped,'failed'=>$this->failed,'errors'=>$this->errors];}
}
