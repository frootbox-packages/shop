<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop;

use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Core\Action\Schema\Title;
use Frootbox\Core\Settings\AsSettings;

/**
 * The settings of the shop (decisions 0311, 0328).
 */
#[AsSettings('Online shop', 'The shop behind the website')]
final readonly class ShopSettings
{
    public function __construct(
        #[Title('Provider'), Description('The class of the provider, e.g. of avaro/shop; empty for the only one installed.')]
        #[Length(max: 200)]
        public string $provider = '',
        #[Title('Shrink images'), Description('Copies the images of the shop no larger than the size below, which saves a lot of space. Off: in the size the shop delivers.')]
        public bool $shrinkImages = true,
        #[Title('Size of images'), Description('Pixels of the longer side of a copied image. A full sync of the catalog brings the images copied before to this size.')]
        #[Range(min: 400, max: 10000)]
        public int $imageSize = 2000,
    ) {}

    /**
     * Pixels of the longer side of a copied image at most, null for the
     * size of the shop.
     */
    public function maxImageEdge(): ?int
    {
        return $this->shrinkImages ? $this->imageSize : null;
    }
}
