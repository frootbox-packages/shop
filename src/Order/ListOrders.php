<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Order;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Shop\Provider\Listing;
use Frootbox\Shop\Provider\OrderSummary;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Shop;

/**
 * @implements Query<Listing<OrderSummary>>
 */
#[AsQuery('frootbox/shop/order.list', 'The orders of the signed-in customer of the shop, newest first', public: true)]
final readonly class ListOrders implements Query
{
    public function __construct(
        #[Length(max: 500)]
        public ?string $cursor = null,
    ) {}

    /**
     * @return Listing<OrderSummary>
     */
    public function handle(Shop $shop): Listing
    {
        return $shop->forCustomer('frootbox/shop/order.list', fn(ShopProvider $provider, string $token): Listing => $provider->orders($token, $this->cursor));
    }
}
