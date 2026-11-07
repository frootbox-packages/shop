<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Blocks;

use Frootbox\Core\Action\ActionException;
use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Url\Urls;
use Frootbox\Shop\Cart\Carts;
use Frootbox\Shop\ShopSession;
use Frootbox\Shop\Website\ShopPages;
use Frootbox\Shop\Website\ShopRendering;

/**
 * The cart of the visitor: products and pieces, for a signed-in
 * customer with prices and totals fresh from the shop (decision 0301).
 */
final readonly class Cart implements BlockController
{
    public function __construct(
        private Carts $carts,
        private ShopSession $session,
        private ShopPages $pages,
        private ShopRendering $rendering,
        private Urls $urls,
    ) {}

    public function data(BlockContext $context): array
    {
        $here = null === $context->target ? '/' : $this->urls->href($context->target, $context->language) ?? '/';
        $error = null;

        try {
            $view = $this->carts->view();
        } catch (ActionException $exception) {
            $view = $this->carts->view(false);
            $error = $exception->template;
        }

        return [
            ...$this->rendering->cart($view, $context->language),
            'error' => null === $error ? null : (string) $error,
            'flash' => $this->session->takeFlash('cart-update'),
            'hidden' => ShopRendering::hidden($here, $context->language),
            'checkout' => $this->pages->href(ShopPages::CHECKOUT, $context->language),
            'catalog' => $this->pages->catalog($context->language),
            'signIn' => (fn(?string $href): ?string => null === $href ? null : $href . '?' . http_build_query(['return' => $here]))($this->pages->href(ShopPages::SIGN_IN, $context->language)),
        ];
    }
}
