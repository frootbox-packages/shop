<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A provider as an extension registers it (decision 0311).
 */
final readonly class ShopProviderEntry
{
    /**
     * @param class-string<ShopProvider> $class
     */
    public function __construct(
        public string $class,
        public string $title,
    ) {}

}
