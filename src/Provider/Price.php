<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * The price of a product for a customer.
 */
final readonly class Price
{
    /**
     * @param list<array{minQuantity: int, unit: UnitPrice}>            $tiers
     * @param array{gross: string, quantity: string, unit: string}|null $basePrice per reference quantity, e.g. per kg
     */
    public function __construct(
        public string $currency,
        public UnitPrice $unit,
        public ?UnitPrice $regularUnit = null,
        public ?string $discountPercentage = null,
        public array $tiers = [],
        public ?array $basePrice = null,
    ) {}
}
