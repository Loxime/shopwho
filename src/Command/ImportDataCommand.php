<?php
namespace App\Command;
use App\Import\ImportManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
#[AsCommand(name: 'app:import:data', description: 'Import synthetic or pseudonymised data from JSON/XLSX.')]
final class ImportDataCommand extends Command
{
    public function __construct(private readonly ImportManager $manager) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addArgument('type', InputArgument::REQUIRED, 'users, products, orders or reviews')->addArgument('file', InputArgument::REQUIRED, 'JSON or XLSX file')->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run all checks and roll back database changes')->addOption('report', null, InputOption::VALUE_REQUIRED, 'Write the import report as JSON');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output); $type = (string) $input->getArgument('type');
        try { $result = $this->manager->import($type, (string) $input->getArgument('file'), (bool) $input->getOption('dry-run')); } catch (\Throwable $e) { $io->error($e->getMessage()); return Command::FAILURE; }
        $io->title(sprintf('Import %s%s', $type, $input->getOption('dry-run') ? ' (dry-run)' : ''));
        $io->table([], [['Total', $result->getTotal()], ['Created', $result->getCreated()], ['Updated', $result->getUpdated()], ['Skipped', $result->getSkipped()], ['Failed', $result->getFailed()]]);
        if ($result->getErrors()) { $io->section('Errors'); foreach ($result->getErrors() as $error) $io->writeln(sprintf('#%d%s — %s', $error->record, $error->externalRef ? ' '.$error->externalRef : '', $error->message)); }
        $report = $input->getOption('report');
        if (is_string($report) && '' !== $report) { $directory = dirname($report); if ((!is_dir($directory) && !mkdir($directory, 0775, true)) || false === file_put_contents($report, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL)) { $io->error(sprintf('Unable to write report "%s".', $report)); return Command::FAILURE; } $io->success(sprintf('Report written to %s.', $report)); }
        return $result->getFailed() > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
