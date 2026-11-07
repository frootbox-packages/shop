<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Customer;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Shop\Provider\Customer;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Shop;

/**
 * @implements Query<Customer>
 */
#[AsQuery('frootbox/shop/customer.show', 'The signed-in customer of the shop with addresses', public: true)]
final readonly class ShowCustomer implements Query
{
    public function handle(Shop $shop): Customer
    {
        return $shop->forCustomer('frootbox/shop/customer.show', static fn(ShopProvider $provider, string $token): Customer => $provider->customer($token));
    }
}
