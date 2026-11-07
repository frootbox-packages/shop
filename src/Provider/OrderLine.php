<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A line of an order.
 */
final readonly class OrderLine
{
    public function __construct(
        public string $title,
        public int $quantity,
        public Amount $total,
        public ?string $sku = null,
    ) {}
}
