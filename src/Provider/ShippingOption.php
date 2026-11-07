<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A way of shipping with its costs.
 */
final readonly class ShippingOption
{
    /**
     * @param int|null $freeFromNet Net value of goods from which shipping is free, in cents
     */
    public function __construct(
        public string $key,
        public string $title,
        public Amount $costs,
        public ?int $freeFromNet = null,
    ) {}
}
