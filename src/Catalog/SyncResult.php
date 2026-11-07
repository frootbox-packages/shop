<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

/**
 * What a sync of the catalog changed.
 */
final readonly class SyncResult
{
    public function __construct(
        public int $products = 0,
        public int $deleted = 0,
        public int $categories = 0,
        public int $images = 0,
        public bool $full = false,
    ) {}

    public function changed(): bool
    {
        return $this->products + $this->deleted + $this->categories + $this->images > 0;
    }
}
