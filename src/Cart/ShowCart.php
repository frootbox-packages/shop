<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Cart;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Length;

/**
 * @implements Query<CartView>
 */
#[AsQuery('frootbox/shop/cart.show', 'The cart of the visitor; for a signed-in customer with prices, shipping and totals from the shop', public: true)]
final readonly class ShowCart implements Query
{
    public function __construct(
        #[Length(max: 100)]
        public ?string $shippingMethod = null,
        #[Length(max: 100)]
        public ?string $deliveryAddressId = null,
    ) {}

    public function handle(Carts $carts): CartView
    {
        return $carts->view(true, $this->shippingMethod, $this->deliveryAddressId);
    }
}
