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
#[AsAction('frootbox/shop/customer.reset-password', 'Sets a new password for a customer of the shop with the token of the link from the mail', public: true, undoable: false)]
final readonly class ResetPassword implements Action
{
    public function __construct(
        #[Length(min: 3, max: 200)]
        public string $email,
        #[Sensitive]
        #[Length(min: 1, max: 500)]
        public string $token,
        #[Sensitive]
        #[Length(min: 8, max: 200)]
        public string $password,
    ) {}

    public function handle(Shop $shop): null
    {
        $shop->call('frootbox/shop/customer.reset-password', fn(ShopProvider $provider) => $provider->resetPassword(trim($this->email), $this->token, $this->password));

        return null;
    }
}
