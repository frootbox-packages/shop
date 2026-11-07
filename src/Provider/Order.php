<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * An order with its lines.
 */
final readonly class Order
{
    /**
     * @param list<OrderLine> $lines
     * @param list<string>    $invoiceIds
     */
    public function __construct(
        public OrderSummary $summary,
        public array $lines,
        public Amount $goods,
        public Amount $shipping,
        public PostalAddress $billingAddress,
        public PostalAddress $deliveryAddress,
        public ?string $shippingMethod = null,
        public ?string $paymentMethod = null,
        public ?string $comment = null,
        public array $invoiceIds = [],
    ) {}
}
