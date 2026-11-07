<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Cart;

use Frootbox\Shop\Provider\Quote;

/**
 * The cart of the visitor; with a signed-in customer the quote with
 * prices and totals, always fresh from the provider (decision 0301).
 */
final readonly class CartView
{
    /**
     * @param list<CartLine> $lines
     */
    public function __construct(
        public array $lines,
        public int $pieces,
        public ?Quote $quote = null,
        public bool $signedIn = false,
    ) {}
}
