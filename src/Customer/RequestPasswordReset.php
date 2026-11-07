<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Customer;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Shop;

/**
 * @implements Action<null>
 */
#[AsAction('frootbox/shop/customer.request-reset', 'Sends a customer of the shop a link to set a new password; says nothing about whether the address is known', public: true, undoable: false)]
final readonly class RequestPasswordReset implements Action
{
    public function __construct(
        #[Length(min: 3, max: 200)]
        public string $email,
        #[Description('The address of the page with the form for the new password')]
        #[Length(max: 2000)]
        public string $returnUrl,
    ) {}

    public function handle(Shop $shop): null
    {
        $shop->call('frootbox/shop/customer.request-reset', fn(ShopProvider $provider) => $provider->requestPasswordReset(trim($this->email), $this->returnUrl));

        return null;
    }
}
