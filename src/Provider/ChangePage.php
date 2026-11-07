<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A page of the changes of the catalog since a position.
 */
final readonly class ChangePage
{
    /**
     * @param list<CatalogChange> $changes
     * @param string|null         $next    The cursor of the next page, null on the last
     * @param string|null         $until   On the last page the position for the next sync
     */
    public function __construct(
        public array $changes,
        public ?string $next = null,
        public ?string $until = null,
    ) {}
}
