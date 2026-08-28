<?php

namespace App\Review;

final readonly class AdminReviewFilter
{
    public const SOURCE_ALL = 'all';
    public const SOURCE_NATIVE = 'native';
    public const SOURCE_IMPORTED = 'imported';

    public const SOURCES = [
        self::SOURCE_ALL,
        self::SOURCE_NATIVE,
        self::SOURCE_IMPORTED,
    ];

    public string $source;

    public ?string $search;

    public ?int $rating;

    public int $page;

    public int $perPage;

    public function __construct(
        ?string $search = null,
        ?int $rating = null,
        string $source = self::SOURCE_ALL,
        int $page = 1,
        int $perPage = 25
    ) {
        $search = null === $search
            ? null
            : trim($search);

        $this->search = null === $search || '' === $search
            ? null
            : mb_substr($search, 0, 120);

        $this->rating = null !== $rating
            && $rating >= 1
            && $rating <= 5
                ? $rating
                : null;

        $this->source = in_array(
            $source,
            self::SOURCES,
            true
        )
            ? $source
            : self::SOURCE_ALL;

        $this->page = max(
            1,
            $page
        );

        $this->perPage = max(
            10,
            min($perPage, 100)
        );
    }
}
