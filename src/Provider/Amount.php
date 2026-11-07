<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A sum in cents, never a float (decision 0311).
 */
final readonly class Amount
{
    public function __construct(
        public int $net,
        public int $tax,
        public int $gross,
    ) {}

    public static function zero(): self
    {
        return new self(0, 0, 0);
    }
}
