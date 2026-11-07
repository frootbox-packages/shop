<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A category of the catalog at the provider.
 */
final readonly class ShopCategory
{
    public function __construct(
        public string $id,
        public ?string $parentId,
        public string $slug,
        public string $title,
        public ?string $description = null,
    ) {}
}
