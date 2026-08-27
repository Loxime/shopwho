<?php

namespace App\Review;

use App\Entity\Review;

final readonly class AdminReviewPage
{
    public int $pageCount;

    /**
     * @param list<Review> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage
    ) {
        $this->pageCount = max(
            1,
            (int) ceil(
                $total / $perPage
            )
        );
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pageCount;
    }

    public function previousPage(): int
    {
        return max(
            1,
            $this->page - 1
        );
    }

    public function nextPage(): int
    {
        return min(
            $this->pageCount,
            $this->page + 1
        );
    }

    public function firstItemNumber(): int
    {
        if (0 === $this->total) {
            return 0;
        }

        return (($this->page - 1) * $this->perPage) + 1;
    }

    public function lastItemNumber(): int
    {
        return min(
            $this->total,
            $this->page * $this->perPage
        );
    }
}
