<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

use DateTimeImmutable;

/**
 * The token of a customer who signed in.
 */
final readonly class CustomerLogin
{
    public function __construct(
        public string $token,
        public DateTimeImmutable $expiresAt,
        public Customer $customer,
    ) {}
}
