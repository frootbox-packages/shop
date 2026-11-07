<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Customer;

/**
 * The signed-in customer as the session keeps it, for templates.
 */
final readonly class SessionCustomer
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $number = '',
        public ?string $company = null,
    ) {}
}
