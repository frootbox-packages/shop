<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop;

use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Title;
use Frootbox\Core\Settings\AsSettings;

/**
 * The settings of the shop (decision 0311).
 */
#[AsSettings('Online shop', 'The shop behind the website')]
final readonly class ShopSettings
{
    public function __construct(
        #[Title('Provider'), Description('The class of the provider, e.g. of avaro/shop; empty for the only one installed.')]
        #[Length(max: 200)]
        public string $provider = '',
    ) {}
}
