<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

use Frootbox\Core\I18n\Formality;

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
        /** How the customer wants to be addressed, null when the shop does not know (decision 0316) */
        public ?Formality $formality = null,
    ) {}
}
