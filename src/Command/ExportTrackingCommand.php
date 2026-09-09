<?php

namespace App\Command;

use App\Enum\TrackingEventType;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:tracking:export-csv',
    description:
        'Exporte les événements pseudonymisés '
        .'dans un dataset CSV reproductible.'
)]
final class ExportTrackingCommand extends Command
{
    private const SCHEMA_VERSION = 1;

    private const CSV_COLUMNS = [
        'id',
        'visitor_id',
        'session_id',
        'event_type',
        'product_id',
        'metadata_json',
        'occurred_at',
    ];

    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Chemin du fichier CSV.',
                'exports/tracking.csv'
            )
            ->addOption(
                'manifest',
                null,
                InputOption::VALUE_REQUIRED,
                'Chemin du manifeste JSON. '
                .'Par défaut, il est placé '
                .'à côté du CSV.'
            )
            ->addOption(
                'since',
                null,
                InputOption::VALUE_REQUIRED,
                'Borne temporelle minimale inclusive '
                .'au format ISO-8601.'
            )
            ->addOption(
                'until',
                null,
                InputOption::VALUE_REQUIRED,
                'Borne temporelle maximale exclusive '
                .'au format ISO-8601.'
            )
            ->addOption(
                'event',
                null,
                InputOption::VALUE_REQUIRED
                    | InputOption::VALUE_IS_ARRAY,
                'Type d’événement à exporter. '
                .'Option répétable.'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $csvPath = trim(
            (string) $input->getOption(
                'output'
            )
        );

        if ($csvPath === '') {
            $output->writeln(
                '<error>Le chemin CSV est vide.</error>'
            );

            return Command::FAILURE;
        }

        $manifestOption =
            $input->getOption(
                'manifest'
            );

        $manifestPath =
            is_string($manifestOption)
            && trim($manifestOption) !== ''
                ? trim($manifestOption)
                : $this->defaultManifestPath(
                    $csvPath
                );

        if ($csvPath === $manifestPath) {
            $output->writeln(
                '<error>Le CSV et le manifeste '
                .'doivent être deux fichiers distincts.'
                .'</error>'
            );

            return Command::FAILURE;
        }

        try {
            $since = $this->parseBoundary(
                $input->getOption('since'),
                'since'
            );

            $until = $this->parseBoundary(
                $input->getOption('until'),
                'until'
            );

            $eventTypes =
                $this->parseEventTypes(
                    $input->getOption(
                        'event'
                    )
                );
        } catch (\InvalidArgumentException $exception) {
            $output->writeln(
                '<error>'
                .$exception->getMessage()
                .'</error>'
            );

            return Command::FAILURE;
        }

        if (
            $since !== null
            && $until !== null
            && $since >= $until
        ) {
            $output->writeln(
                '<error>La borne --since '
                .'doit précéder --until.</error>'
            );

            return Command::FAILURE;
        }

        $sql = <<<'SQL'
SELECT
    id,
    visitor_id,
    session_id,
    event_type,
    product_id,
    metadata,
    occurred_at
FROM tracking_event
WHERE 1 = 1
SQL;

        $params = [];
        $types = [];

        if ($since !== null) {
            $sql .= <<<'SQL'

  AND occurred_at >= :since
SQL;

            $params['since'] = $since;
            $types['since'] =
                Types::DATETIME_IMMUTABLE;
        }

        if ($until !== null) {
            $sql .= <<<'SQL'

  AND occurred_at < :until
SQL;

            $params['until'] = $until;
            $types['until'] =
                Types::DATETIME_IMMUTABLE;
        }

        if ($eventTypes !== []) {
            $sql .= <<<'SQL'

  AND event_type IN (:event_types)
SQL;

            $params['event_types'] =
                array_map(
                    static fn (
                        TrackingEventType $event
                    ): string =>
                        $event->value,
                    $eventTypes
                );

            $types['event_types'] =
                ArrayParameterType::STRING;
        }

        $sql .= <<<'SQL'

ORDER BY occurred_at ASC, id ASC
SQL;

        $rows =
            $this->connection
                ->fetchAllAssociative(
                    $sql,
                    $params,
                    $types
                );

        try {
            $this->ensureDirectory(
                dirname($csvPath)
            );

            $this->ensureDirectory(
                dirname($manifestPath)
            );

            $this->writeCsv(
                $csvPath,
                $rows
            );

            $sha256 = hash_file(
                'sha256',
                $csvPath
            );

            if ($sha256 === false) {
                throw new \RuntimeException(
                    'Impossible de calculer '
                    .'le SHA-256 du CSV.'
                );
            }

            $manifest = [
                'dataset' =>
                    'shopwho_tracking_events',
                'schema_version' =>
                    self::SCHEMA_VERSION,
                'generated_at' =>
                    (new \DateTimeImmutable(
                        'now',
                        new \DateTimeZone('UTC')
                    ))->format(DATE_ATOM),
                'filters' => [
                    'since_inclusive' =>
                        $since?->format(
                            DATE_ATOM
                        ),
                    'until_exclusive' =>
                        $until?->format(
                            DATE_ATOM
                        ),
                    'event_types' =>
                        array_map(
                            static fn (
                                TrackingEventType $event
                            ): string =>
                                $event->value,
                            $eventTypes
                        ),
                ],
                'row_count' =>
                    count($rows),
                'sha256' =>
                    $sha256,
                'csv_file' =>
                    basename($csvPath),
                'columns' =>
                    self::CSV_COLUMNS,
            ];

            $this->writeManifest(
                $manifestPath,
                $manifest
            );
        } catch (\Throwable $exception) {
            $output->writeln(
                '<error>'
                .$exception->getMessage()
                .'</error>'
            );

            return Command::FAILURE;
        }

        $output->writeln(
            sprintf(
                '<info>%d événements exportés vers %s</info>',
                count($rows),
                $csvPath
            )
        );

        $output->writeln(
            'SHA-256: '.$sha256
        );

        $output->writeln(
            'Manifeste: '.$manifestPath
        );

        return Command::SUCCESS;
    }

    /**
     * @return list<TrackingEventType>
     */
    private function parseEventTypes(
        mixed $rawEvents
    ): array {
        if (!is_array($rawEvents)) {
            return [];
        }

        $events = [];

        foreach ($rawEvents as $rawEvent) {
            if (!is_string($rawEvent)) {
                throw new \InvalidArgumentException(
                    'Type d’événement invalide.'
                );
            }

            $rawEvent = trim(
                $rawEvent
            );

            $event =
                TrackingEventType::tryFrom(
                    $rawEvent
                );

            if ($event === null) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Événement inconnu : %s',
                        $rawEvent
                    )
                );
            }

            $events[$event->value] =
                $event;
        }

        return array_values(
            $events
        );
    }

    private function parseBoundary(
        mixed $rawValue,
        string $option
    ): ?\DateTimeImmutable {
        if ($rawValue === null) {
            return null;
        }

        if (!is_string($rawValue)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Valeur --%s invalide.',
                    $option
                )
            );
        }

        $rawValue = trim(
            $rawValue
        );

        if ($rawValue === '') {
            return null;
        }

        try {
            if (
                preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $rawValue
                ) === 1
            ) {
                $date =
                    new \DateTimeImmutable(
                        $rawValue
                        .'T00:00:00+00:00'
                    );
            } else {
                $date =
                    new \DateTimeImmutable(
                        $rawValue
                    );
            }
        } catch (\Exception) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Date --%s invalide : %s',
                    $option,
                    $rawValue
                )
            );
        }

        return $date->setTimezone(
            new \DateTimeZone('UTC')
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function writeCsv(
        string $path,
        array $rows
    ): void {
        $temporaryPath =
            $path
            .'.tmp.'
            .bin2hex(
                random_bytes(6)
            );

        $handle = fopen(
            $temporaryPath,
            'wb'
        );

        if ($handle === false) {
            throw new \RuntimeException(
                'Impossible d’ouvrir '
                .'le fichier CSV temporaire.'
            );
        }

        try {
            fputcsv(
                $handle,
                self::CSV_COLUMNS,
                ',',
                '"',
                ''
            );

            foreach ($rows as $row) {
                fputcsv(
                    $handle,
                    [
                        $row['id'],
                        $row['visitor_id'],
                        $row['session_id'],
                        $row['event_type'],
                        $row['product_id'],
                        $row['metadata'],
                        $row['occurred_at'],
                    ],
                    ',',
                    '"',
                    ''
                );
            }
        } finally {
            fclose($handle);
        }

        if (!rename(
            $temporaryPath,
            $path
        )) {
            @unlink(
                $temporaryPath
            );

            throw new \RuntimeException(
                'Impossible de finaliser '
                .'le fichier CSV.'
            );
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeManifest(
        string $path,
        array $manifest
    ): void {
        $json = json_encode(
            $manifest,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        );

        $temporaryPath =
            $path
            .'.tmp.'
            .bin2hex(
                random_bytes(6)
            );

        if (
            file_put_contents(
                $temporaryPath,
                $json.PHP_EOL
            ) === false
        ) {
            throw new \RuntimeException(
                'Impossible d’écrire '
                .'le manifeste temporaire.'
            );
        }

        if (!rename(
            $temporaryPath,
            $path
        )) {
            @unlink(
                $temporaryPath
            );

            throw new \RuntimeException(
                'Impossible de finaliser '
                .'le manifeste.'
            );
        }
    }

    private function ensureDirectory(
        string $directory
    ): void {
        if (
            $directory === ''
            || $directory === '.'
            || is_dir($directory)
        ) {
            return;
        }

        if (
            !mkdir(
                $directory,
                0775,
                true
            )
            && !is_dir($directory)
        ) {
            throw new \RuntimeException(
                sprintf(
                    'Impossible de créer '
                    .'le dossier %s.',
                    $directory
                )
            );
        }
    }

    private function defaultManifestPath(
        string $csvPath
    ): string {
        if (
            str_ends_with(
                strtolower($csvPath),
                '.csv'
            )
        ) {
            return substr(
                $csvPath,
                0,
                -4
            ).'.manifest.json';
        }

        return $csvPath
            .'.manifest.json';
    }
}
