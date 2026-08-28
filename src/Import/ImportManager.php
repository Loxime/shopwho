<?php

namespace App\Import;

use App\Import\Exception\ImportException;
use App\Import\Importer\DataImporterInterface;
use App\Import\Importer\ImportOutcome;
use App\Import\Reader\ImportReaderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ImportManager
{
    public const SUPPORTED_TYPES = ImportSchema::TYPES;

    public const SUPPORTED_EXTENSIONS = ImportSchema::FORMATS;

    /**
     * @param iterable<ImportReaderInterface> $readers
     * @param iterable<DataImporterInterface> $importers
     */
    public function __construct(
        #[AutowireIterator(ImportReaderInterface::class)]
        private iterable $readers,
        #[AutowireIterator(DataImporterInterface::class)]
        private iterable $importers,
        private ValidatorInterface $validator,
        private EntityManagerInterface $em
    ) {
    }

    public function import(
        string $type,
        string $file,
        bool $dryRun = false
    ): ImportResult {
        if (
            !in_array(
                $type,
                self::SUPPORTED_TYPES,
                true
            )
        ) {
            throw new ImportException(
                sprintf(
                    'Unknown import type "%s".',
                    $type
                )
            );
        }

        $extension = strtolower(
            pathinfo(
                $file,
                PATHINFO_EXTENSION
            )
        );

        if (
            !in_array(
                $extension,
                self::SUPPORTED_EXTENSIONS,
                true
            )
        ) {
            throw new ImportException(
                sprintf(
                    'Unsupported file format ".%s".',
                    $extension
                )
            );
        }

        $reader = null;

        foreach ($this->readers as $candidate) {
            if (
                $candidate->supports(
                    $extension
                )
            ) {
                $reader = $candidate;

                break;
            }
        }

        if (null === $reader) {
            throw new ImportException(
                sprintf(
                    'Unsupported file format ".%s".',
                    $extension
                )
            );
        }

        $importer = null;

        foreach ($this->importers as $candidate) {
            if ($candidate->supports($type)) {
                $importer = $candidate;

                break;
            }
        }

        if (null === $importer) {
            throw new ImportException(
                sprintf(
                    'No importer for "%s".',
                    $type
                )
            );
        }

        $payload = $reader->read(
            $type,
            $file
        );

        $result = new ImportResult();

        foreach ($payload->errors as $error) {
            $result->countTotal();

            $result->failed(
                $error->record,
                $error->externalRef,
                $error->message
            );
        }

        $connection = $this
            ->em
            ->getConnection();

        if ($dryRun) {
            $connection->beginTransaction();
        }

        try {
            foreach ($payload->records as $dto) {
                $result->countTotal();

                $reference = property_exists(
                    $dto,
                    'externalRef'
                )
                    ? $dto->externalRef
                    : null;

                $violations = $this
                    ->validator
                    ->validate($dto);

                if (count($violations) > 0) {
                    $messages = [];

                    foreach (
                        $violations as $violation
                    ) {
                        $messages[] = sprintf(
                            '%s: %s',
                            $violation
                                ->getPropertyPath(),
                            $violation
                                ->getMessage()
                        );
                    }

                    $result->failed(
                        $dto->record,
                        $reference,
                        implode(
                            '; ',
                            $messages
                        )
                    );

                    continue;
                }

                try {
                    $outcome = $importer
                        ->import($dto);

                    match ($outcome) {
                        ImportOutcome::Created =>
                            $result->created(),
                        ImportOutcome::Updated =>
                            $result->updated(),
                        ImportOutcome::Skipped =>
                            $result->skipped(),
                    };
                } catch (
                    \DomainException
                    | \InvalidArgumentException
                    $exception
                ) {
                    $result->failed(
                        $dto->record,
                        $reference,
                        $exception->getMessage()
                    );
                }
            }
        } finally {
            if (
                $dryRun
                && $connection
                    ->isTransactionActive()
            ) {
                $connection->rollBack();
                $this->em->clear();
            }
        }

        return $result;
    }
}
