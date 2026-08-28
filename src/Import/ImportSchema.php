<?php

namespace App\Import;

use App\Import\Exception\ImportException;

final class ImportSchema
{
    public const TYPES = [
        'users',
        'categories',
        'products',
        'orders',
        'reviews',
    ];

    public const FORMATS = [
        'json',
        'xlsx',
    ];

    private const FIELDS = [
        'users' => [
            'externalRef',
            'email',
            'firstName',
            'lastName',
            'createdAt',
        ],
        'categories' => [
            'externalRef',
            'name',
            'slug',
            'icon',
            'isFeatured',
            'showInNavigation',
            'navigationPosition',
        ],
        'products' => [
            'externalRef',
            'name',
            'slug',
            'description',
            'priceCents',
            'stock',
            'categorySlug',
            'imageUrl',
            'isActive',
        ],
        'orders' => [
            'externalRef',
            'userExternalRef',
            'status',
            'orderedAt',
            'totalCents',
        ],
        'order_items' => [
            'orderExternalRef',
            'productExternalRef',
            'productNameSnapshot',
            'productSlugSnapshot',
            'quantity',
            'unitPriceCents',
        ],
        'reviews' => [
            'externalRef',
            'userExternalRef',
            'productExternalRef',
            'rating',
            'comment',
            'createdAt',
        ],
    ];

    private const EXAMPLES = [
        'users' => [
            'externalRef' => 'USR-EXAMPLE-001',
            'email' => 'alice@example.test',
            'firstName' => 'Alice',
            'lastName' => 'Martin',
            'createdAt' => '2026-08-01T10:00:00+00:00',
        ],
        'categories' => [
            'externalRef' => 'CAT-EXAMPLE-001',
            'name' => 'Catégorie exemple',
            'slug' => 'categorie-exemple',
            'icon' => 'fa-solid fa-box',
            'isFeatured' => true,
            'showInNavigation' => true,
            'navigationPosition' => 10,
        ],
        'products' => [
            'externalRef' => 'PROD-EXAMPLE-001',
            'name' => 'Produit exemple',
            'slug' => 'produit-exemple',
            'description' => 'Description du produit exemple.',
            'priceCents' => 1299,
            'stock' => 20,
            'categorySlug' => 'categorie-existante',
            'imageUrl' => 'https://example.test/product.jpg',
            'isActive' => true,
        ],
        'orders' => [
            'externalRef' => 'ORD-EXAMPLE-001',
            'userExternalRef' => 'USR-EXAMPLE-001',
            'status' => 'completed',
            'orderedAt' => '2026-08-15T14:30:00+00:00',
            'totalCents' => 1299,
        ],
        'order_items' => [
            'orderExternalRef' => 'ORD-EXAMPLE-001',
            'productExternalRef' => 'PROD-EXAMPLE-001',
            'productNameSnapshot' => 'Produit exemple',
            'productSlugSnapshot' => 'produit-exemple',
            'quantity' => 1,
            'unitPriceCents' => 1299,
        ],
        'reviews' => [
            'externalRef' => 'REV-EXAMPLE-001',
            'userExternalRef' => 'USR-EXAMPLE-001',
            'productExternalRef' => 'PROD-EXAMPLE-001',
            'rating' => 5,
            'comment' => 'Très bon produit.',
            'createdAt' => '2026-08-20T09:00:00+00:00',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function sheetsFor(
        string $type
    ): array {
        self::assertType($type);

        return 'orders' === $type
            ? ['orders', 'order_items']
            : [$type];
    }

    /**
     * @return list<string>
     */
    public static function fieldsFor(
        string $sheet
    ): array {
        if (!isset(self::FIELDS[$sheet])) {
            throw new ImportException(
                sprintf(
                    'Unsupported import sheet "%s".',
                    $sheet
                )
            );
        }

        return self::FIELDS[$sheet];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exampleFor(
        string $sheet
    ): array {
        if (!isset(self::EXAMPLES[$sheet])) {
            throw new ImportException(
                sprintf(
                    'Unsupported import sheet "%s".',
                    $sheet
                )
            );
        }

        return self::EXAMPLES[$sheet];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function jsonTemplate(
        string $type
    ): array {
        $template = [];

        foreach (
            self::sheetsFor($type)
            as $sheet
        ) {
            $template[$sheet] = [
                self::exampleFor($sheet),
            ];
        }

        return $template;
    }

    public static function assertType(
        string $type
    ): void {
        if (
            !in_array(
                $type,
                self::TYPES,
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
    }
}
