<?php
namespace App\Tests\Import;
use App\Import\DTO\ImportDtoFactory;use App\Import\DTO\UserImportDto;use App\Import\Exception\ImportException;use App\Import\Reader\JsonImportReader;use App\Import\Reader\XlsxImportReader;use PHPUnit\Framework\TestCase;
final class ImportReaderTest extends TestCase
{
 private string $fixtures;
 protected function setUp():void{$this->fixtures=dirname(__DIR__).'/Fixtures/Import';}
 public function testValidJsonUsers():void{$payload=(new JsonImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/users.json');self::assertCount(1,$payload->records);self::assertInstanceOf(UserImportDto::class,$payload->records[0]);self::assertSame('USR-FICTION-001',$payload->records[0]->externalRef);}
 public function testValidXlsxUsers():void{$payload=(new XlsxImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/users.xlsx');self::assertCount(1,$payload->records);self::assertSame('USR-FICTION-001',$payload->records[0]->externalRef);}
 public function testJsonAndXlsxProduceEquivalentDtos():void{$json=(new JsonImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/users.json')->records[0];$xlsx=(new XlsxImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/users.xlsx')->records[0];self::assertEquals([$json->externalRef,$json->email,$json->firstName,$json->lastName,$json->createdAt],[$xlsx->externalRef,$xlsx->email,$xlsx->firstName,$xlsx->lastName,$xlsx->createdAt]);}
 public function testMissingXlsxHeaderIsExplicit():void{$this->expectException(ImportException::class);$this->expectExceptionMessage('missing header(s): createdAt');(new XlsxImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/users-missing-header.xlsx');}
 public function testInvalidJsonIsExplicit():void{$this->expectException(ImportException::class);$this->expectExceptionMessage('Invalid JSON');(new JsonImportReader(new ImportDtoFactory()))->read('users',$this->fixtures.'/invalid.json');}
}
