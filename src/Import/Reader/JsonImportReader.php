<?php
namespace App\Import\Reader;
use App\Import\DTO\ImportDtoFactory;
use App\Import\DTO\OrderImportDto;
use App\Import\Exception\ImportException;
use App\Import\ImportPayload;
final readonly class JsonImportReader implements ImportReaderInterface {
 public function __construct(private ImportDtoFactory $factory) {}
 public function supports(string $extension): bool{return 'json'===strtolower($extension);}
 public function read(string $type,string $file):ImportPayload {
  if(!is_file($file)||!is_readable($file))throw new ImportException(sprintf('File "%s" is not readable.',$file));
  try{$data=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new ImportException('Invalid JSON: '.$e->getMessage());}
  if(!is_array($data)||!isset($data[$type])||!is_array($data[$type]))throw new ImportException(sprintf('JSON root key "%s" must contain an array.',$type));
  $records=[]; foreach(array_values($data[$type]) as $i=>$row){if(!is_array($row))throw new ImportException(sprintf('Record #%d must be an object.',$i+1));$row['_record']=$i+1;$records[]=$this->factory->create($type,$row,$i+1);}
  if('orders'===$type){$items=[];foreach(array_values($data['order_items']??[]) as $i=>$row){if(!is_array($row))throw new ImportException(sprintf('Order item #%d must be an object.',$i+1));$row['_record']=$i+1;$item=$this->factory->create('order_items',$row,$i+1);$items[$item->orderExternalRef][]=$item;} $records=array_map(static fn(OrderImportDto $o)=>$o->withItems($items[$o->externalRef]??[]),$records);}
  return new ImportPayload($type,$records);
 }
}
