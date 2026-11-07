<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A product or a category that changed or is gone.
 */
final readonly class CatalogChange
{
    public function __construct(
        public ChangeKind $kind,
        public string $id,
        public bool $deleted = false,
    ) {}
}
