<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

/**
 * What a sync of the catalog changed, or one step of it (decision 0324).
 */
final readonly class SyncResult
{
    public function __construct(
        public int $products = 0,
        public int $deleted = 0,
        public int $categories = 0,
        public int $images = 0,
        public bool $full = false,
        /** Copies of images that no product uses any more */
        public int $removedImages = 0,
        /** The sync is done; otherwise the next step goes on */
        public bool $done = true,
    ) {}

    /**
     * This and the next step together.
     */
    public function plus(self $next): self
    {
        return new self(
            $this->products + $next->products,
            $this->deleted + $next->deleted,
            $this->categories + $next->categories,
            $this->images + $next->images,
            $this->full || $next->full,
            $this->removedImages + $next->removedImages,
            $next->done,
        );
    }

    public function changed(): bool
    {
        return $this->products + $this->deleted + $this->categories + $this->images + $this->removedImages > 0;
    }
}
