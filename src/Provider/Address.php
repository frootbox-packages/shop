<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * An address of a customer.
 */
final readonly class Address
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $company = null,
        public ?string $addition = null,
        public string $street = '',
        public string $postalCode = '',
        public string $city = '',
        public string $country = '',
    ) {}

    /**
     * The lines to print, without empty ones.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return PostalAddress::compact([$this->company, $this->name, $this->addition, $this->street, trim($this->postalCode . ' ' . $this->city), $this->country]);
    }
}
