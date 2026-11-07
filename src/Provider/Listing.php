<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A page of a list with the cursor of the next one.
 *
 * @template T
 */
final readonly class Listing
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public ?string $next = null,
    ) {}
}
