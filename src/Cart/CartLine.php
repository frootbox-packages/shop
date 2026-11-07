<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Cart;

use Frootbox\Shop\Provider\Amount;
use Frootbox\Shop\Provider\UnitPrice;

/**
 * A line of the cart with the product of the mirror and, for a
 * signed-in customer, its price.
 */
final readonly class CartLine
{
    /**
     * @param list<string> $problems
     */
    public function __construct(
        public string $productId,
        public string $providerId,
        public string $title,
        public string $sku,
        public string $slug,
        public ?string $image,
        public int $quantity,
        public int $minQuantity,
        public int $quantityStep,
        public bool $orderable,
        public ?UnitPrice $unit = null,
        public ?Amount $total = null,
        public array $problems = [],
    ) {}
}
