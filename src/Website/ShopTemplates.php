<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Website;

use Frootbox\Shop\Cart\Carts;
use Frootbox\Shop\ShopSession;

/**
 * shop_customer() and shop_cart() for the head and foot of every page
 * (decision 0311), without asking the provider.
 *
 *     {% set customer = shop_customer() %}  name, email, number, company; null for a guest
 *     {{ shop_cart().pieces }}              pieces in the cart
 */
final readonly class ShopTemplates
{
    public function __construct(
        private ShopSession $session,
        private Carts $carts,
        private ShopPages $pages,
    ) {}

    /**
     * @return array{name: string, email: string, number: string, company: string|null}|null
     */
    public function customer(): ?array
    {
        $customer = $this->session->customer();

        return null === $customer ? null : ['name' => $customer->name, 'email' => $customer->email, 'number' => $customer->number, 'company' => $customer->company];
    }

    /**
     * @return array{pieces: int, href: string|null}
     */
    public function cart(): array
    {
        return ['pieces' => $this->carts->pieces(), 'href' => $this->pages->href(ShopPages::CART)];
    }
}
