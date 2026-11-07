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
use Frootbox\Shop\ShopSession;

/**
 * @implements Action<SessionCustomer>
 */
#[AsAction('frootbox/shop/customer.sign-in', 'Signs a customer of the shop in for the visitor of the website', public: true, undoable: false)]
final readonly class SignIn implements Action
{
    public function __construct(
        #[Length(min: 3, max: 200)]
        public string $email,
        #[Sensitive]
        #[Length(min: 1, max: 200)]
        public string $password,
    ) {}

    public function handle(Shop $shop, ShopSession $session): SessionCustomer
    {
        $session->require();
        $login = $shop->call('frootbox/shop/customer.sign-in', fn(ShopProvider $provider) => $provider->signIn(trim($this->email), $this->password));
        $session->signIn($login);

        return $session->customer() ?? throw Shop::signIn();
    }
}
