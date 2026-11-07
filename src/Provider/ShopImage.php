<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * An image at the provider. The version changes only with the file.
 */
final readonly class ShopImage
{
    public function __construct(
        public string $id,
        public string $url,
        public string $version,
        public ?string $alt = null,
    ) {}
}
