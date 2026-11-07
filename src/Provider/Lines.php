<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * The lines of a cart: pieces by id of the product at the provider.
 */
final readonly class Lines
{
    /**
     * @param array<int|string, int> $quantities ids that look like numbers are int keys in PHP
     */
    public function __construct(public array $quantities) {}

    /**
     * @return list<array{productId: string, quantity: int}>
     */
    public function toList(): array
    {
        $lines = [];

        foreach ($this->quantities as $id => $quantity) {
            $lines[] = ['productId' => (string) $id, 'quantity' => $quantity];
        }

        return $lines;
    }
}
