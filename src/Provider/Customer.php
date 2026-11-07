<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A signed-in customer.
 */
final readonly class Customer
{
    /**
     * @param list<Address> $deliveryAddresses
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $number = '',
        public ?string $company = null,
        public ?string $vatId = null,
        public ?Address $billingAddress = null,
        public array $deliveryAddresses = [],
    ) {}
}
