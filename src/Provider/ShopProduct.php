<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A product of the catalog at the provider, without prices. Quantities
 * are pieces, e.g. a pack of 6.
 */
final readonly class ShopProduct
{
    /**
     * @param list<string>    $categories  ids of categories at the provider
     * @param list<ShopImage> $images
     * @param string|null     $description HTML of the provider, sanitised by the module
     * @param list<string>    $tags        tags at the provider, e.g. "startseite"; tags of the core after the sync
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $sku,
        public string $title,
        public array $categories = [],
        public array $images = [],
        public Stock $stock = Stock::Unknown,
        public bool $orderable = true,
        public ?string $gtin = null,
        public ?string $shortDescription = null,
        public ?string $description = null,
        public ?string $deliveryTime = null,
        public string $unit = 'EA',
        public int $minQuantity = 1,
        public int $quantityStep = 1,
        public array $tags = [],
    ) {}
}
