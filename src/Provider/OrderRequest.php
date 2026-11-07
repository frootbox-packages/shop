<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * An order as the customer placed it.
 */
final readonly class OrderRequest
{
    /**
     * @param int                                       $expectedGross The total gross the customer saw, in cents
     * @param list<array{key: string, version: string}> $consents
     */
    public function __construct(
        public Lines $lines,
        public string $shippingMethod,
        public string $paymentMethod,
        public int $expectedGross,
        public ?string $deliveryAddressId = null,
        public ?string $reference = null,
        public ?string $comment = null,
        public array $consents = [],
    ) {}
}
