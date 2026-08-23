<?php
namespace App\Import\Reader;
use App\Import\DTO\ImportDtoFactory;
use App\Import\DTO\OrderImportDto;
use App\Import\Exception\ImportException;
use App\Import\ImportError;
use App\Import\ImportPayload;
final readonly class JsonImportReader implements ImportReaderInterface {
 public function __construct(private ImportDtoFactory $factory) {}
 public function supports(string $extension): bool{return 'json'===strtolower($extension);}
 public function read(string $type,string $file):ImportPayload {
  if(!is_file($file)||!is_readable($file))throw new ImportException(sprintf('File "%s" is not readable.',$file));
  try{$data=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new ImportException('Invalid JSON: '.$e->getMessage());}
  if(!is_array($data)||!isset($data[$type])||!is_array($data[$type]))throw new ImportException(sprintf('JSON root key "%s" must contain an array.',$type));
  $records=[];$errors=[];$knownOrderRefs=[];
  foreach(array_values($data[$type]) as $i=>$row){$record=$i+1;if(!is_array($row)){$errors[]=new ImportError($record,null,sprintf('Record #%d must be an object.',$record));continue;}$row['_record']=$record;$ref=$this->reference($row,'externalRef');if('orders'===$type&&null!==$ref)$knownOrderRefs[$ref]=true;try{$records[]=$this->factory->create($type,$row,$record);}catch(ImportException $e){$errors[]=new ImportError($record,$ref,$e->getMessage());}}
  if('orders'===$type){[$records,$orderErrors]=$this->assembleOrders($records,$errors,$knownOrderRefs,$data['order_items']??[]);$errors=$orderErrors;}
  return new ImportPayload($type,$records,$errors);
 }
 /** @param list<object> $orders @param list<ImportError> $errors @param array<string,true> $knownOrderRefs @param mixed $sourceItems @return array{list<OrderImportDto>,list<ImportError>} */
 private function assembleOrders(array $orders,array $errors,array $knownOrderRefs,mixed $sourceItems):array
 {
  if(!is_array($sourceItems))throw new ImportException('JSON root key "order_items" must contain an array.');
  $items=[];$itemErrors=[];
  foreach(array_values($sourceItems) as $i=>$row){$record=$i+1;if(!is_array($row)){$errors[]=new ImportError($record,null,sprintf('Order item #%d must be an object.',$record));continue;}$row['_record']=$record;$orderRef=$this->reference($row,'orderExternalRef');try{$item=$this->factory->create('order_items',$row,$record);$items[$item->orderExternalRef][]=$item;}catch(ImportException $e){if(null!==$orderRef&&isset($knownOrderRefs[$orderRef]))$itemErrors[$orderRef][]=new ImportError($record,$orderRef,$e->getMessage());else$errors[]=new ImportError($record,$orderRef,$e->getMessage());}}
  $valid=[];foreach($orders as $order){assert($order instanceof OrderImportDto);if(isset($itemErrors[$order->externalRef])){$first=$itemErrors[$order->externalRef][0];$errors[]=new ImportError($first->record,$order->externalRef,$first->message);continue;}$valid[]=$order->withItems($items[$order->externalRef]??[]);}
  foreach($items as $orderRef=>$orderItems){if(isset($knownOrderRefs[$orderRef]))continue;foreach($orderItems as $item)$errors[]=new ImportError($item->record,$orderRef,sprintf('Unknown orderExternalRef "%s".',$orderRef));}
  return [$valid,$errors];
 }
 /** @param array<string,mixed> $row */
 private function reference(array $row,string $key):?string{$value=$row[$key]??null;if(!is_scalar($value))return null;$value=trim((string)$value);return ''===$value?null:$value;}
}
