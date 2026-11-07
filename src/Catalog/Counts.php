<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

/**
 * What one step of a sync did so far.
 *
 * @internal
 */
final class Counts
{
    public int $products = 0;

    public int $deleted = 0;

    public int $categories = 0;

    public int $images = 0;

    public int $removedImages = 0;

    /** It asked the provider for categories or a product */
    public bool $work = false;

    public function worked(): bool
    {
        return $this->work;
    }

    public function result(bool $full, bool $done): SyncResult
    {
        return new SyncResult($this->products, $this->deleted, $this->categories, $this->images, $full, $this->removedImages, $done);
    }
}
