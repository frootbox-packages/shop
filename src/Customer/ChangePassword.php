<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Customer;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Sensitive;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Shop;

/**
 * @implements Action<null>
 */
#[AsAction('frootbox/shop/customer.change-password', 'Changes the password of the signed-in customer of the shop', public: true, undoable: false)]
final readonly class ChangePassword implements Action
{
    public function __construct(
        #[Sensitive]
        #[Length(min: 1, max: 200)]
        public string $currentPassword,
        #[Sensitive]
        #[Length(min: 8, max: 200)]
        public string $password,
    ) {}

    public function handle(Shop $shop): null
    {
        $shop->forCustomer('frootbox/shop/customer.change-password', fn(ShopProvider $provider, string $token) => $provider->changePassword($token, $this->currentPassword, $this->password));

        return null;
    }
}
