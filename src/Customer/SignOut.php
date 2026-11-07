<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Customer;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Shop\Provider\Providers;
use Frootbox\Shop\Provider\ShopException;
use Frootbox\Shop\ShopSession;

/**
 * @implements Action<null>
 */
#[AsAction('frootbox/shop/customer.sign-out', 'Signs the customer of the shop out; the cart stays', public: true, undoable: false)]
final readonly class SignOut implements Action
{
    public function handle(Providers $providers, ShopSession $session): null
    {
        $session->require();
        $token = $session->token();

        if (null !== $token) {
            try {
                $providers->active()?->signOut($token);
            } catch (ShopException) {
                // Signed out here anyway.
            }
        }

        $session->signOut();

        return null;
    }
}
