<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Order;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Shop\Provider\Order;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Shop;

/**
 * @implements Query<Order>
 */
#[AsQuery('frootbox/shop/order.show', 'An order of the signed-in customer of the shop with its lines', public: true)]
final readonly class ShowOrder implements Query
{
    public function __construct(
        #[Length(min: 1, max: 100)]
        public string $id,
    ) {}

    public function handle(Shop $shop): Order
    {
        return $shop->forCustomer('frootbox/shop/order.show', fn(ShopProvider $provider, string $token): Order => $provider->order($token, $this->id));
    }
}
