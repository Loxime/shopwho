<?php
namespace App\Tests\Import;
use App\Import\DTO\ImportDtoFactory;use App\Import\DTO\UserImportDto;use App\Import\Exception\ImportException;use App\Import\Reader\JsonImportReader;use App\Import\Reader\XlsxImportReader;use OpenSpout\Common\Entity\Row;use OpenSpout\Writer\XLSX\Writer;use PHPUnit\Framework\TestCase;
final class ImportReaderTest extends TestCase
{
 private string $fixtures;
 protected function setUp():void{$this->fixtures=dirname(__DIR__).'/Fixtures/Import';}
 public function testValidJsonUsers():void{$payload=(new JsonImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/users.json');self::assertCount(1,$payload->records);self::assertInstanceOf(UserImportDto::class,$payload->records[0]);self::assertSame('USR-FICTION-001',$payload->records[0]->externalRef);}
 public function testValidXlsxUsers():void{$payload=(new XlsxImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/users.xlsx');self::assertCount(1,$payload->records);self::assertSame('USR-FICTION-001',$payload->records[0]->externalRef);}
 public function testJsonAndXlsxProduceEquivalentDtos():void{$json=(new JsonImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/users.json')->records[0];$xlsx=(new XlsxImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/users.xlsx')->records[0];self::assertEquals([$json->externalRef,$json->email,$json->firstName,$json->lastName,$json->createdAt],[$xlsx->externalRef,$xlsx->email,$xlsx->firstName,$xlsx->lastName,$xlsx->createdAt]);}
 public function testMissingXlsxHeaderIsExplicit():void{$this->expectException(ImportException::class);$this->expectExceptionMessage('missing header(s): createdAt');(new XlsxImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/users-missing-header.xlsx');}
 public function testInvalidJsonIsExplicit():void{$this->expectException(ImportException::class);$this->expectExceptionMessage('Invalid JSON');(new JsonImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/invalid.json');}
 public function testInvalidTypedXlsxRowDoesNotHideFollowingRows():void
 {
  $rows=[['externalRef','name','slug','description','priceCents','stock','categorySlug','imageUrl','isActive'],['PROD-1','One','one','One',100,1,'category',null,true],['PROD-2','Bad','bad','Bad','12abc',1,'category',null,true],['PROD-3','Three','three','Three',300,1,'category',null,true]];
  $file=sys_get_temp_dir().'/shopwho-invalid-typed-'.bin2hex(random_bytes(5)).'.xlsx';$writer=new Writer();$writer->openToFile($file);$writer->getCurrentSheet()->setName('products');$writer->addRows(array_map(static fn(array $values)=>Row::fromValues($values),$rows));$writer->close();
  try{$payload=(new XlsxImportReader(new ImportDtoFactory()))->read('products',$file);}finally{unlink($file);}
  self::assertCount(2,$payload->records);self::assertSame(['PROD-1','PROD-3'],array_map(static fn($dto)=>$dto->externalRef,$payload->records));self::assertCount(1,$payload->errors);self::assertSame(3,$payload->errors[0]->record);self::assertSame('PROD-2',$payload->errors[0]->externalRef);
 }
}
