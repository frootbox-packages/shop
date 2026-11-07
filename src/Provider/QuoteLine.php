<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A line of a quote.
 */
final readonly class QuoteLine
{
    /**
     * @param list<string> $problems e.g. "not-orderable", "quantity"
     */
    public function __construct(
        public string $productId,
        public ?string $sku,
        public ?string $title,
        public int $quantity,
        public UnitPrice $unit,
        public Amount $total,
        public array $problems = [],
    ) {}
}
