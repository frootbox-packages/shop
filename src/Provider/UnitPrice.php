<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A price per unit as decimal strings with four places, e.g. "12.4500".
 */
final readonly class UnitPrice
{
    public function __construct(
        public string $net,
        public string $gross,
        public string $taxRate,
    ) {}
}
