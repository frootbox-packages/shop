<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Website;

use Frootbox\Core\Plugin\PluginPages;
use Frootbox\Core\Url\BasePages;
use Frootbox\Core\Url\Target;
use Frootbox\Core\Url\Urls;
use Frootbox\Shop\Catalog\Products;

/**
 * The addresses of the pages of the shop: the first page with each
 * block, the catalog the page of the plugin.
 */
final readonly class ShopPages
{
    public const string CART = 'frootbox/shop/Cart';

    public const string CHECKOUT = 'frootbox/shop/Checkout';

    public const string SIGN_IN = 'frootbox/shop/SignIn';

    public const string ACCOUNT = 'frootbox/shop/Account';

    public function __construct(
        private BasePages $pages,
        private PluginPages $plugins,
        private Urls $urls,
    ) {}

    public function href(string $block, ?string $language = null): ?string
    {
        $pages = $this->pages->of([$block]);
        $id = null === $language ? (array_values($pages)[0] ?? null) : $pages[$language] ?? null;

        return null === $id ? null : $this->urls->href(Target::page($id), $language);
    }

    public function catalog(?string $language = null): ?string
    {
        $id = $this->plugins->of(Products::TYPE);

        return null === $id ? null : $this->urls->href(Target::page($id), $language);
    }

    public function product(string $id, ?string $language = null): ?string
    {
        return $this->urls->href(Target::parse(Products::TYPE . ':' . $id), $language);
    }

    /**
     * @return array{cart: string|null, checkout: string|null, signIn: string|null, account: string|null, catalog: string|null}
     */
    public function all(?string $language = null): array
    {
        return [
            'cart' => $this->href(self::CART, $language),
            'checkout' => $this->href(self::CHECKOUT, $language),
            'signIn' => $this->href(self::SIGN_IN, $language),
            'account' => $this->href(self::ACCOUNT, $language),
            'catalog' => $this->catalog($language),
        ];
    }
}
