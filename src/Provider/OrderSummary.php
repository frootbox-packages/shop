<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

use DateTimeImmutable;

/**
 * An order in a list.
 */
final readonly class OrderSummary
{
    public function __construct(
        public string $id,
        public string $number,
        public OrderStatus $status,
        public string $currency,
        public Amount $total,
        public DateTimeImmutable $createdAt,
        public ?string $reference = null,
    ) {}
}
