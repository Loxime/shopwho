<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:tracking:export-csv', description: 'Exporte les événements pseudonymisés en CSV pour analyse locale.')]
class ExportTrackingCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Chemin du CSV', 'exports/tracking.csv')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Date ISO minimale, ex. 2026-08-01');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getOption('output');
        $since = $input->getOption('since');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $sql = 'SELECT id, visitor_id, session_id, event_type, product_id, metadata, occurred_at FROM tracking_event';
        $params = [];
        if ($since) {
            $sql .= ' WHERE occurred_at >= :since';
            $params['since'] = $since;
        }
        $sql .= ' ORDER BY occurred_at ASC, id ASC';

        $rows = $this->connection->fetchAllAssociative($sql, $params);
        $handle = fopen($path, 'wb');
        if (!$handle) {
            $output->writeln('<error>Impossible d’ouvrir le fichier de sortie.</error>');
            return Command::FAILURE;
        }

        fputcsv($handle, ['id', 'visitor_id', 'session_id', 'event_type', 'product_id', 'metadata_json', 'occurred_at']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['id'], $row['visitor_id'], $row['session_id'], $row['event_type'], $row['product_id'], $row['metadata'], $row['occurred_at'],
            ]);
        }
        fclose($handle);

        $output->writeln(sprintf('<info>%d événements exportés vers %s</info>', count($rows), $path));
        return Command::SUCCESS;
    }
}
