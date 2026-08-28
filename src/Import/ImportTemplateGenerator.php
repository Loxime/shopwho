<?php

namespace App\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class ImportTemplateGenerator
{
    public function json(
        string $type
    ): string {
        return json_encode(
            ImportSchema::jsonTemplate($type),
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        ).PHP_EOL;
    }

    public function xlsx(
        string $type
    ): string {
        $sheets = ImportSchema::sheetsFor(
            $type
        );

        $path = sprintf(
            '%s/shopwho-import-template-%s.xlsx',
            sys_get_temp_dir(),
            bin2hex(random_bytes(12))
        );

        $writer = new Writer();

        try {
            $writer->openToFile($path);

            foreach (
                $sheets as $index => $sheetName
            ) {
                if (0 === $index) {
                    $sheet = $writer
                        ->getCurrentSheet();
                } else {
                    $sheet = $writer
                        ->addNewSheetAndMakeItCurrent();
                }

                $sheet->setName(
                    $sheetName
                );

                $writer->addRow(
                    Row::fromValues(
                        ImportSchema::fieldsFor(
                            $sheetName
                        )
                    )
                );

                $writer->addRow(
                    Row::fromValues(
                        array_values(
                            ImportSchema::exampleFor(
                                $sheetName
                            )
                        )
                    )
                );
            }

            $writer->close();
        } catch (\Throwable $exception) {
            if (is_file($path)) {
                @unlink($path);
            }

            throw $exception;
        }

        return $path;
    }
}
