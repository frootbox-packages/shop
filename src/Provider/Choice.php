<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * Something to choose, e.g. a method of payment.
 */
final readonly class Choice
{
    public function __construct(
        public string $key,
        public string $title,
    ) {}
}
