<?php

namespace App\DataReset;

use App\DataReset\Policy\ProductResetPolicy;
use App\DataReset\Policy\ResetDecision;
use App\DataReset\Policy\UserResetPolicy;
use App\DataReset\Reader\JsonResetReader;
use App\DataReset\Reader\ResetPayload;
use App\DataReset\Reader\ResetReaderInterface;
use App\DataReset\Reader\XlsxResetReader;
use App\Entity\Product;
use App\Entity\User;
use App\Import\Exception\ImportException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DataResetManager
{
    /** @var list<ResetReaderInterface> */
    private array $readers;

    public function __construct(
        private EntityManagerInterface $em,
        private UserResetPolicy $userPolicy,
        private ProductResetPolicy $productPolicy,
        JsonResetReader $jsonReader,
        XlsxResetReader $xlsxReader,
    ) {
        $this->readers = [$jsonReader, $xlsxReader];
    }

    public function previewFile(string $type, string $file): ResetResult
    {
        [$resetType, $payload] = $this->read($type, $file);

        return $this->evaluate($resetType, $payload->references, $payload->issues, false, false);
    }

    public function applyFile(string $type, string $file): ResetResult
    {
        [$resetType, $payload] = $this->read($type, $file);

        return $this->applyReferences($resetType->value, $payload->references, $payload->issues);
    }

    /** @param list<string> $references @param list<ResetEntry> $issues */
    public function previewReferences(string $type, array $references, array $issues = []): ResetResult
    {
        return $this->evaluate($this->type($type), $references, $issues, false, false);
    }

    /** @param list<string> $references @param list<ResetEntry> $issues */
    public function applyReferences(string $type, array $references, array $issues = []): ResetResult
    {
        $resetType = $this->type($type);
        // A preflight result is intentionally not trusted by the transaction below.
        $this->evaluate($resetType, $references, $issues, false, false);

        return $this->em->wrapInTransaction(fn (): ResetResult => $this->evaluate($resetType, $references, $issues, true, true));
    }

    /** @return array{ResetType,ResetPayload} */
    public function read(string $type, string $file, ?string $extension = null): array
    {
        $resetType = $this->type($type);
        $extension = strtolower($extension ?? pathinfo($file, PATHINFO_EXTENSION));
        foreach ($this->readers as $reader) {
            if ($reader->supports($extension)) {
                return [$resetType, $reader->read($resetType, $file)];
            }
        }
        throw new ImportException(sprintf('Unsupported reset file extension "%s". Expected json or xlsx.', $extension));
    }

    /** @param list<string> $references @param list<ResetEntry> $issues */
    private function evaluate(ResetType $type, array $references, array $issues, bool $delete, bool $lock): ResetResult
    {
        $entries = [];
        foreach ($references as $externalRef) {
            $entity = $this->em->getRepository($this->entityClass($type))->findOneBy(['externalRef' => $externalRef]);
            if (null === $entity) {
                $entries[] = new ResetEntry($externalRef, 'not_found', 'not_found');
                continue;
            }
            if ($lock) {
                $this->em->lock($entity, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($entity);
            }
            $decision = $this->decision($type, $entity);
            if (!$decision->deletable) {
                $entries[] = new ResetEntry($externalRef, 'protected', $decision->reason, null, $decision->relatedCount);
                continue;
            }
            if ($delete) {
                $this->em->remove($entity);
            }
            $entries[] = new ResetEntry($externalRef, $delete ? 'deleted' : 'deletable');
        }
        if ($delete) {
            $this->em->flush();
        }

        return new ResetResult([...$entries, ...$issues]);
    }

    private function decision(ResetType $type, User|Product $entity): ResetDecision
    {
        return match ($type) {
            ResetType::Users => $this->userPolicy->decide($entity),
            ResetType::Products => $this->productPolicy->decide($entity),
        };
    }

    private function entityClass(ResetType $type): string
    {
        return ResetType::Users === $type ? User::class : Product::class;
    }

    private function type(string $type): ResetType
    {
        return ResetType::tryFrom($type) ?? throw new \InvalidArgumentException('Reset type must be "users" or "products".');
    }
}
