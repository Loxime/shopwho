<?php

namespace App\Tests\DataReset;

use App\DataReset\Reader\JsonResetReader;
use App\DataReset\Reader\ReferenceNormalizer;
use App\DataReset\Reader\XlsxResetReader;
use App\DataReset\ResetType;
use App\Import\Exception\ImportException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

final class ResetReaderTest extends TestCase
{
    public function testJsonNormalizesReferencesAndReportsDuplicateAndEmptyRecords(): void
    {
        $payload = (new JsonResetReader(new ReferenceNormalizer()))->read(ResetType::Users, dirname(__DIR__).'/Fixtures/DataReset/users.json');

        self::assertSame(['USR-FICTION-001', 'USR-FICTION-002'], $payload->references);
        self::assertSame(['duplicate', 'failed'], array_map(static fn ($entry) => $entry->status, $payload->issues));
    }

    public function testJsonRequiresCorrectRoot(): void
    {
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('products');
        (new JsonResetReader(new ReferenceNormalizer()))->read(ResetType::Products, dirname(__DIR__).'/Fixtures/DataReset/users.json');
    }

    public function testJsonReadsProductsContract(): void
    {
        $payload = (new JsonResetReader(new ReferenceNormalizer()))->read(ResetType::Products, dirname(__DIR__).'/Fixtures/DataReset/products.json');
        self::assertSame(['PROD-FICTION-001', 'PROD-FICTION-404'], $payload->references);
        self::assertSame([], $payload->issues);
    }

    public function testXlsxReadsExpectedSheetAndHeader(): void
    {
        $file = $this->xlsx('users', [['externalRef'], [' USR-FICTION-XLSX '], ['USR-FICTION-XLSX']]);
        $payload = (new XlsxResetReader(new ReferenceNormalizer()))->read(ResetType::Users, $file);

        self::assertSame(['USR-FICTION-XLSX'], $payload->references);
        self::assertSame('duplicate', $payload->issues[0]->status);
    }

    public function testXlsxRequiresExternalRefHeader(): void
    {
        $file = $this->xlsx('products', [['name'], ['Produit FICTION']]);
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('externalRef');
        (new XlsxResetReader(new ReferenceNormalizer()))->read(ResetType::Products, $file);
    }

    public function testXlsxReadsProductsContract(): void
    {
        $file = $this->xlsx('products', [['externalRef'], ['PROD-FICTION-XLSX']]);
        $payload = (new XlsxResetReader(new ReferenceNormalizer()))->read(ResetType::Products, $file);
        self::assertSame(['PROD-FICTION-XLSX'], $payload->references);
        self::assertSame([], $payload->issues);
    }

    private function xlsx(string $sheetName, array $rows): string
    {
        $file = sys_get_temp_dir().'/shopwho-reset-'.bin2hex(random_bytes(5)).'.xlsx';
        $writer = new Writer();
        $writer->openToFile($file);
        $writer->getCurrentSheet()->setName($sheetName);
        foreach ($rows as $values) {
            $writer->addRow(Row::fromValues($values));
        }
        $writer->close();

        return $file;
    }
}
