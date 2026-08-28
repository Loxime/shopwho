<?php
namespace App\Import\Reader;
use App\Import\DTO\ImportDtoFactory;
use App\Import\DTO\OrderImportDto;
use App\Import\Exception\ImportException;
use App\Import\ImportError;
use App\Import\ImportPayload;
use OpenSpout\Reader\XLSX\Reader;
use App\Import\ImportSchema;

final readonly class XlsxImportReader implements ImportReaderInterface {

 public function __construct(private ImportDtoFactory $factory){}
 public function supports(string $extension):bool{return 'xlsx'===strtolower($extension);}
 public function read(string $type,string $file):ImportPayload {
  if(!is_file($file)||!is_readable($file))throw new ImportException(sprintf('File "%s" is not readable.',$file));
  $wanted='orders'===$type?['orders','order_items']:[$type];$found=[];$reader=new Reader();
  try{$reader->open($file);foreach($reader->getSheetIterator() as $sheet){$name=$sheet->getName();if(!in_array($name,$wanted,true))continue;$found[$name]=$this->readSheet($sheet,$name);} }catch(ImportException $e){throw $e;}catch(\Throwable $e){throw new ImportException('Invalid XLSX: '.$e->getMessage());}finally{$reader->close();}
  foreach($wanted as $sheet){if(!isset($found[$sheet]))throw new ImportException(sprintf('Required sheet "%s" is missing.',$sheet));}
  $records=$found[$type]['records'];$errors=$found[$type]['errors'];if('orders'===$type){[$records,$errors]=$this->assembleOrders($records,$errors,$found['orders']['refs'],$found['order_items']);}
  return new ImportPayload($type,$records,$errors);
 }
 /** @return array{records:list<object>,errors:list<ImportError>,refs:array<string,true>} */
 private function readSheet(object $sheet,string $type):array {$headers=null;$out=[];$errors=[];$refs=[];$line=0;foreach($sheet->getRowIterator() as $row){$line++;$values=$row->toArray();if(array_filter($values,static fn($v)=>null!==$v&&''!==trim((string)$v))===[])continue;if(null===$headers){$headers=array_map(static fn($v)=>trim((string)$v),$values);$missing = array_diff(ImportSchema::fieldsFor($type),$headers); if($missing)throw new ImportException(sprintf('Sheet "%s": missing header(s): %s.',$type,implode(', ',$missing)));continue;}$assoc=[];foreach($headers as $i=>$header){if(''!==$header)$assoc[$header]=$values[$i]??null;}$assoc['_record']=$line;$ref=$this->reference($assoc,'order_items'===$type?'orderExternalRef':'externalRef');if('orders'===$type&&null!==$ref)$refs[$ref]=true;try{$out[]=$this->factory->create($type,$assoc,$line);}catch(ImportException $e){$errors[]=new ImportError($line,$ref,$e->getMessage());}}if(null===$headers)throw new ImportException(sprintf('Sheet "%s" is empty.',$type));return ['records'=>$out,'errors'=>$errors,'refs'=>$refs];}
 /** @param list<OrderImportDto> $orders @param list<ImportError> $errors @param array<string,true> $knownOrderRefs @param array{records:list<object>,errors:list<ImportError>,refs:array<string,true>} $itemSheet @return array{list<OrderImportDto>,list<ImportError>} */
 private function assembleOrders(array $orders,array $errors,array $knownOrderRefs,array $itemSheet):array
 {
  $items=[];foreach($itemSheet['records'] as $item)$items[$item->orderExternalRef][]=$item;
  $itemErrors=[];foreach($itemSheet['errors'] as $error){if(null!==$error->externalRef&&isset($knownOrderRefs[$error->externalRef]))$itemErrors[$error->externalRef][]=$error;else$errors[]=$error;}
  $valid=[];foreach($orders as $order){if(isset($itemErrors[$order->externalRef])){$first=$itemErrors[$order->externalRef][0];$errors[]=new ImportError($first->record,$order->externalRef,$first->message);continue;}$valid[]=$order->withItems($items[$order->externalRef]??[]);}
  foreach($items as $orderRef=>$orderItems){if(isset($knownOrderRefs[$orderRef]))continue;foreach($orderItems as $item)$errors[]=new ImportError($item->record,$orderRef,sprintf('Unknown orderExternalRef "%s".',$orderRef));}
  return [$valid,$errors];
 }
 /** @param array<string,mixed> $row */
 private function reference(array $row,string $key):?string{$value=$row[$key]??null;if(!is_scalar($value))return null;$value=trim((string)$value);return ''===$value?null:$value;}
}
