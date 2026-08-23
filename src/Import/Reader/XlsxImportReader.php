<?php
namespace App\Import\Reader;
use App\Import\DTO\ImportDtoFactory;
use App\Import\DTO\OrderImportDto;
use App\Import\Exception\ImportException;
use App\Import\ImportPayload;
use OpenSpout\Reader\XLSX\Reader;
final readonly class XlsxImportReader implements ImportReaderInterface {
 private const HEADERS=['users'=>['externalRef','email','firstName','lastName','createdAt'],'products'=>['externalRef','name','slug','description','priceCents','stock','categorySlug','imageUrl','isActive'],'orders'=>['externalRef','userExternalRef','status','orderedAt','totalCents'],'order_items'=>['orderExternalRef','productExternalRef','productNameSnapshot','productSlugSnapshot','quantity','unitPriceCents'],'reviews'=>['externalRef','userExternalRef','productExternalRef','rating','comment','createdAt']];
 public function __construct(private ImportDtoFactory $factory){}
 public function supports(string $extension):bool{return 'xlsx'===strtolower($extension);}
 public function read(string $type,string $file):ImportPayload {
  if(!is_file($file)||!is_readable($file))throw new ImportException(sprintf('File "%s" is not readable.',$file));
  $wanted='orders'===$type?['orders','order_items']:[$type];$found=[];$reader=new Reader();
  try{$reader->open($file);foreach($reader->getSheetIterator() as $sheet){$name=$sheet->getName();if(!in_array($name,$wanted,true))continue;$found[$name]=$this->readSheet($sheet,$name);} }catch(ImportException $e){throw $e;}catch(\Throwable $e){throw new ImportException('Invalid XLSX: '.$e->getMessage());}finally{$reader->close();}
  foreach($wanted as $sheet){if(!isset($found[$sheet]))throw new ImportException(sprintf('Required sheet "%s" is missing.',$sheet));}
  $records=$found[$type];if('orders'===$type){$items=[];foreach($found['order_items'] as $item)$items[$item->orderExternalRef][]=$item;$records=array_map(static fn(OrderImportDto $o)=>$o->withItems($items[$o->externalRef]??[]),$records);}
  return new ImportPayload($type,$records);
 }
 private function readSheet(object $sheet,string $type):array {$headers=null;$out=[];$line=0;foreach($sheet->getRowIterator() as $row){$line++;$values=$row->toArray();if(array_filter($values,static fn($v)=>null!==$v&&''!==trim((string)$v))===[])continue;if(null===$headers){$headers=array_map(static fn($v)=>trim((string)$v),$values);$missing=array_diff(self::HEADERS[$type],$headers);if($missing)throw new ImportException(sprintf('Sheet "%s": missing header(s): %s.',$type,implode(', ',$missing)));continue;}$assoc=[];foreach($headers as $i=>$header){if(''!==$header)$assoc[$header]=$values[$i]??null;}$assoc['_record']=$line;$out[]=$this->factory->create($type,$assoc,$line);}if(null===$headers)throw new ImportException(sprintf('Sheet "%s" is empty.',$type));return $out;}
}
