<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * Prices and totals of a cart for a customer, always fresh from the
 * provider (decision 0301).
 */
final readonly class Quote
{
    /**
     * @param list<QuoteLine>      $lines
     * @param list<ShippingOption> $shippingMethods
     * @param list<Choice>         $paymentMethods
     * @param int                  $minimumMissing  Net in cents still missing to the minimum order
     */
    public function __construct(
        public string $currency,
        public array $lines,
        public Amount $goods,
        public array $shippingMethods,
        public ?string $shippingMethod,
        public Amount $shipping,
        public array $paymentMethods,
        public Amount $total,
        public bool $valid,
        public ?int $minimumNet = null,
        public int $minimumMissing = 0,
    ) {}
}
