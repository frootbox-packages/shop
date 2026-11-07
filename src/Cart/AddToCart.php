<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Cart;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Core\Support\Message;
use Frootbox\Shop\ShopSession;

/**
 * @implements Action<int>
 */
#[AsAction('frootbox/shop/cart.add', 'Puts pieces of a product into the cart of the visitor; returns the pieces of it now in the cart', public: true, undoable: false)]
final readonly class AddToCart implements Action
{
    public function __construct(
        #[Description('The id of the product')]
        public string $product,
        #[Range(min: 1, max: 100000)]
        public int $quantity = 1,
    ) {}

    public function handle(Carts $carts, ShopSession $session): int
    {
        $session->require();
        $product = $carts->product($this->product) ?? throw new InvalidInputException('frootbox/shop/cart.add', ['/product' => new Message('There is no such product.')]);

        if (!$product->orderable) {
            throw new InvalidInputException('frootbox/shop/cart.add', ['/product' => new Message('This product cannot be ordered right now.')]);
        }

        return $carts->add($product, $this->quantity);
    }
}
