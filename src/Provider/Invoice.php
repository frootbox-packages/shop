<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

use DateTimeImmutable;

/**
 * An invoice or credit note of a customer.
 */
final readonly class Invoice
{
    public function __construct(
        public string $id,
        public string $number,
        public string $kind,
        public InvoiceStatus $status,
        public string $currency,
        public Amount $total,
        public DateTimeImmutable $date,
        public ?DateTimeImmutable $dueDate = null,
    ) {}
}
