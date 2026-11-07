<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * An address on an order; fields may be empty.
 */
final readonly class PostalAddress
{
    public function __construct(
        public ?string $name = null,
        public ?string $company = null,
        public ?string $addition = null,
        public ?string $street = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $country = null,
    ) {}

    /**
     * The lines to print, without empty ones.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return self::compact([$this->company, $this->name, $this->addition, $this->street, trim(($this->postalCode ?? '') . ' ' . ($this->city ?? '')), $this->country]);
    }

    /**
     * @param list<string|null> $lines
     *
     * @return list<string>
     */
    public static function compact(array $lines): array
    {
        return array_values(array_filter(array_map(static fn(?string $line): string => trim($line ?? ''), $lines), static fn(string $line): bool => '' !== $line));
    }
}
