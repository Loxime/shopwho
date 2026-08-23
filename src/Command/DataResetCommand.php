<?php

namespace App\Command;

use App\DataReset\DataResetManager;
use App\DataReset\ResetEntry;
use App\DataReset\ResetResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:data:reset', description: 'Preview or delete selected imported users/products safely.')]
final class DataResetCommand extends Command
{
    public function __construct(private readonly DataResetManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'users or products')
            ->addArgument('file', InputArgument::REQUIRED, 'JSON or XLSX reset file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without mutation')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Apply deletions explicitly');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $apply = (bool) $input->getOption('apply');
        if ($dryRun === $apply) {
            $io->error('Choose exactly one mode: --dry-run or --apply.');
            return Command::INVALID;
        }

        try {
            $result = $dryRun
                ? $this->manager->previewFile((string) $input->getArgument('type'), (string) $input->getArgument('file'))
                : $this->manager->applyFile((string) $input->getArgument('type'), (string) $input->getArgument('file'));
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->title(sprintf('Data reset: %s (%s)', $input->getArgument('type'), $dryRun ? 'dry-run' : 'apply'));
        $io->table([], [
            ['Total', $result->getTotal()],
            ['Deletable', $result->getDeletable()],
            ['Deleted', $result->getDeleted()],
            ['Protected', $result->getProtected()],
            ['Not found', $result->getNotFound()],
            ['Failed', $result->getFailed()],
            ['Skipped duplicates', $result->getSkipped()],
        ]);
        $this->renderDetails($io, $result);

        return $result->getFailed() > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function renderDetails(SymfonyStyle $io, ResetResult $result): void
    {
        $details = array_map(static fn (ResetEntry $entry): array => [
            $entry->externalRef ?? sprintf('record #%d', $entry->record),
            $entry->status,
            $entry->reason ?? '',
        ], $result->entries);
        if ([] !== $details) {
            $io->section('Details');
            $io->table(['externalRef', 'status', 'reason'], $details);
        }
    }
}
