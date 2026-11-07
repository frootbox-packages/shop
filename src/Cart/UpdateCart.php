<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Cart;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Shop\ShopSession;

/**
 * @implements Action<int>
 */
#[AsAction('frootbox/shop/cart.update', 'Sets the pieces of products in the cart of the visitor, 0 removes one; returns the pieces in the cart', public: true, undoable: false)]
final readonly class UpdateCart implements Action
{
    /**
     * @param array<mixed> $quantities
     */
    public function __construct(
        #[Description('Pieces by id of the product, e.g. {"01J…": 12}')]
        public array $quantities,
    ) {}

    public function handle(Carts $carts, ShopSession $session): int
    {
        $session->require();

        foreach ($this->quantities as $id => $quantity) {
            $product = $carts->product((string) $id);

            if (null !== $product && is_numeric($quantity)) {
                $carts->set($product, max(0, (int) $quantity));
            }
        }

        return $carts->pieces();
    }
}
