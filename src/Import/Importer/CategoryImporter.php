<?php

namespace App\Import\Importer;

use App\Entity\Category;
use App\Enum\DataOrigin;
use App\Import\DTO\CategoryImportDto;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CategoryImporter implements DataImporterInterface
{
    public function __construct(
        private CategoryRepository $categories,
        private EntityManagerInterface $em,
    ) {
    }

    public function supports(string $type): bool
    {
        return 'categories' === $type;
    }

    public function import(object $dto): ImportOutcome
    {
        assert($dto instanceof CategoryImportDto);

        $category = $this->categories->findOneBy([
            'externalRef' => $dto->externalRef,
        ]);

        if (
            $category instanceof Category
            && DataOrigin::Native === $category->getDataOrigin()
        ) {
            throw new \DomainException(
                'Native category is protected from import updates.'
            );
        }

        $slugOwner = $this->categories->findOneBy([
            'slug' => $dto->slug,
        ]);

        if (
            $slugOwner instanceof Category
            && $slugOwner !== $category
        ) {
            throw new \DomainException(
                'Slug already used by another category.'
            );
        }

        $outcome = $category instanceof Category
            ? ImportOutcome::Updated
            : ImportOutcome::Created;

        if (!$category instanceof Category) {
            $category = (new Category())
                ->setDataOrigin(DataOrigin::Imported)
                ->setExternalRef($dto->externalRef);

            $this->em->persist($category);
        }

        $category
            ->setName($dto->name)
            ->setSlug($dto->slug)
            ->setIcon($dto->icon)
            ->setIsFeatured($dto->isFeatured)
            ->setShowInNavigation(
                $dto->showInNavigation
            )
            ->setNavigationPosition(
                $dto->navigationPosition
            );

        $this->em->flush();

        return $outcome;
    }
}
