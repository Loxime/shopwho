<?php

namespace App\Tests\Command;

use App\Kernel;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class ExportTrackingCommandTest extends KernelTestCase
{
    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true
        );
    }

    private Connection $connection;

    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $this->connection =
            static::getContainer()
                ->get(Connection::class);

        $this->cleanEvents();
    }

    protected function tearDown(): void
    {
        $this->cleanEvents();

        foreach (
            $this->temporaryFiles
            as $file
        ) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testExportRespectsWindowAndEventFilter(): void
    {
        $this->insertEvent(
            'a',
            'PRODUCT_VIEW',
            '2026-09-05 12:00:00'
        );

        $this->insertEvent(
            'b',
            'ADD_TO_CART',
            '2026-09-06 12:00:00'
        );

        $this->insertEvent(
            'c',
            'PRODUCT_VIEW',
            '2026-09-10 12:00:00'
        );

        [
            $csvPath,
            $manifestPath,
        ] = $this->temporaryPaths();

        $tester =
            $this->commandTester();

        $status = $tester->execute(
            [
                '--output' => $csvPath,
                '--manifest' =>
                    $manifestPath,
                '--since' =>
                    '2026-09-01',
                '--until' =>
                    '2026-09-08',
                '--event' => [
                    'PRODUCT_VIEW',
                ],
            ]
        );

        self::assertSame(
            Command::SUCCESS,
            $status
        );

        self::assertFileExists(
            $csvPath
        );

        self::assertFileExists(
            $manifestPath
        );

        $rows =
            $this->readCsv(
                $csvPath
            );

        self::assertCount(
            2,
            $rows
        );

        self::assertSame(
            [
                'id',
                'visitor_id',
                'session_id',
                'event_type',
                'product_id',
                'metadata_json',
                'occurred_at',
            ],
            $rows[0]
        );

        self::assertSame(
            'export-test-a',
            $rows[1][1]
        );

        self::assertSame(
            'PRODUCT_VIEW',
            $rows[1][3]
        );

        $manifest = json_decode(
            (string) file_get_contents(
                $manifestPath
            ),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        self::assertSame(
            'shopwho_tracking_events',
            $manifest['dataset']
        );

        self::assertSame(
            1,
            $manifest['schema_version']
        );

        self::assertSame(
            1,
            $manifest['row_count']
        );

        self::assertSame(
            [
                'PRODUCT_VIEW',
            ],
            $manifest[
                'filters'
            ]['event_types']
        );

        self::assertSame(
            hash_file(
                'sha256',
                $csvPath
            ),
            $manifest['sha256']
        );
    }

    public function testUntilBoundaryIsExclusive(): void
    {
        $this->insertEvent(
            'before-until',
            'PRODUCT_VIEW',
            '2026-09-07 23:59:59'
        );

        $this->insertEvent(
            'at-until',
            'PRODUCT_VIEW',
            '2026-09-08 00:00:00'
        );

        [
            $csvPath,
            $manifestPath,
        ] = $this->temporaryPaths();

        $status =
            $this->commandTester()
                ->execute(
                    [
                        '--output' =>
                            $csvPath,
                        '--manifest' =>
                            $manifestPath,
                        '--until' =>
                            '2026-09-08',
                    ]
                );

        self::assertSame(
            Command::SUCCESS,
            $status
        );

        $rows =
            $this->readCsv(
                $csvPath
            );

        self::assertCount(
            2,
            $rows
        );

        self::assertSame(
            'export-test-before-until',
            $rows[1][1]
        );
    }

    public function testUnknownEventIsRejected(): void
    {
        [
            $csvPath,
            $manifestPath,
        ] = $this->temporaryPaths();

        $tester =
            $this->commandTester();

        $status = $tester->execute(
            [
                '--output' => $csvPath,
                '--manifest' =>
                    $manifestPath,
                '--event' => [
                    'UNKNOWN_EVENT',
                ],
            ]
        );

        self::assertSame(
            Command::FAILURE,
            $status
        );

        self::assertStringContainsString(
            'Événement inconnu',
            $tester->getDisplay()
        );

        self::assertFileDoesNotExist(
            $csvPath
        );

        self::assertFileDoesNotExist(
            $manifestPath
        );
    }

    public function testInvalidWindowIsRejected(): void
    {
        [
            $csvPath,
            $manifestPath,
        ] = $this->temporaryPaths();

        $tester =
            $this->commandTester();

        $status = $tester->execute(
            [
                '--output' => $csvPath,
                '--manifest' =>
                    $manifestPath,
                '--since' =>
                    '2026-09-10',
                '--until' =>
                    '2026-09-01',
            ]
        );

        self::assertSame(
            Command::FAILURE,
            $status
        );

        self::assertStringContainsString(
            'doit précéder',
            $tester->getDisplay()
        );
    }

    private function commandTester(): CommandTester
    {
        $application =
            new Application(
                self::$kernel
            );

        return new CommandTester(
            $application->find(
                'app:tracking:export-csv'
            )
        );
    }

    private function insertEvent(
        string $suffix,
        string $eventType,
        string $occurredAt
    ): void {
        $this->connection
            ->executeStatement(
                <<<'SQL'
INSERT INTO tracking_event (
    visitor_id,
    session_id,
    event_type,
    product_id,
    user_id,
    metadata,
    occurred_at
)
VALUES (
    :visitor_id,
    :session_id,
    :event_type,
    NULL,
    NULL,
    CAST(:metadata AS JSON),
    :occurred_at
)
SQL,
                [
                    'visitor_id' =>
                        'export-test-'
                        .$suffix,
                    'session_id' =>
                        'export-session-'
                        .$suffix,
                    'event_type' =>
                        $eventType,
                    'metadata' =>
                        '{"source":"export-test"}',
                    'occurred_at' =>
                        $occurredAt,
                ]
            );
    }

    /**
     * @return array{string, string}
     */
    private function temporaryPaths(): array
    {
        $suffix =
            bin2hex(
                random_bytes(8)
            );

        $base =
            sys_get_temp_dir()
            .'/shopwho-export-test-'
            .$suffix;

        $csvPath =
            $base.'.csv';

        $manifestPath =
            $base.'.manifest.json';

        $this->temporaryFiles[] =
            $csvPath;

        $this->temporaryFiles[] =
            $manifestPath;

        return [
            $csvPath,
            $manifestPath,
        ];
    }

    /**
     * @return list<list<string|null>>
     */
    private function readCsv(
        string $path
    ): array {
        $handle = fopen(
            $path,
            'rb'
        );

        self::assertNotFalse(
            $handle
        );

        $rows = [];

        try {
            while (
                (
                    $row = fgetcsv(
                        $handle,
                        null,
                        ',',
                        '"',
                        ''
                    )
                ) !== false
            ) {
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function cleanEvents(): void
    {
        $this->connection
            ->executeStatement(
                <<<'SQL'
DELETE FROM tracking_event
WHERE visitor_id LIKE 'export-test-%'
SQL
            );
    }
}
