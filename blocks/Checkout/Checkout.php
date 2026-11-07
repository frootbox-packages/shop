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
use Frootbox\Core\View\Delivery;
use Frootbox\Shop\Cart\Carts;
use Frootbox\Shop\Provider\Address;
use Frootbox\Shop\Provider\Choice;
use Frootbox\Shop\Provider\LegalText;
use Frootbox\Shop\Provider\ShippingOption;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Shop;
use Frootbox\Shop\ShopSession;
use Frootbox\Shop\Website\ShopPages;
use Frootbox\Shop\Website\ShopRendering;

/**
 * The checkout: delivery address, shipping, payment, the legal texts to
 * agree to and the order with the total the customer sees. Only for a
 * signed-in customer; the quote comes fresh from the shop.
 */
final readonly class Checkout implements BlockController
{
    public function __construct(
        private Carts $carts,
        private Shop $shop,
        private ShopSession $session,
        private ShopPages $pages,
        private ShopRendering $rendering,
        private Urls $urls,
        private Delivery $delivery,
    ) {}

    public function data(BlockContext $context): array
    {
        $this->delivery->withoutCache();
        $here = null === $context->target ? '/' : $this->urls->href($context->target, $context->language) ?? '/';
        $flash = $this->session->takeFlash('order');
        $signIn = $this->pages->href(ShopPages::SIGN_IN, $context->language);
        $base = [
            'flash' => $flash,
            'hidden' => ShopRendering::hidden($here, $context->language),
            'cart' => $this->pages->href(ShopPages::CART, $context->language),
            'account' => $this->pages->href(ShopPages::ACCOUNT, $context->language),
            'signIn' => null === $signIn ? null : $signIn . '?' . http_build_query(['return' => $here]),
        ];

        if (null === $this->session->customer()) {
            return ['state' => 'signed-out', ...$base];
        }

        $shipping = $context->query('shipping');
        $address = $context->query('address');

        try {
            $customer = $this->shop->forCustomer('frootbox/shop/customer.show', static fn(ShopProvider $provider, string $token) => $provider->customer($token));
            $channel = $this->shop->call('frootbox/shop/order.place', static fn(ShopProvider $provider) => $provider->channel());
            $view = $this->carts->view(true, '' === $shipping ? null : $shipping, '' === $address ? null : $address);
        } catch (ActionException $error) {
            return ['state' => 'error', 'error' => (string) $error->template, ...$base];
        }

        if (null === $view->quote) {
            return ['state' => true === ($flash['success'] ?? false) ? 'done' : 'empty', ...$base];
        }

        $quote = $view->quote;
        $currency = $quote->currency;

        return [
            'state' => 'ready',
            ...$this->rendering->cart($view, $context->language),
            ...$base,
            'customer' => ['name' => $customer->name, 'company' => $customer->company, 'number' => $customer->number, 'billing' => $customer->billingAddress?->lines() ?? []],
            'addresses' => array_map(static fn(Address $item): array => ['id' => $item->id, 'text' => implode(', ', $item->lines()), 'current' => $item->id === $address], $customer->deliveryAddresses),
            'shippingMethods' => array_map(fn(ShippingOption $option): array => ['key' => $option->key, 'title' => $option->title, 'costs' => $this->rendering->amount($option->costs, $currency, $context->language)['net'], 'current' => $option->key === $quote->shippingMethod], $quote->shippingMethods),
            'paymentMethods' => array_map(static fn(Choice $choice): array => ['key' => $choice->key, 'title' => $choice->title], $quote->paymentMethods),
            'legal' => array_map(static fn(LegalText $text): array => ['key' => $text->key, 'title' => $text->title, 'url' => $text->url, 'required' => $text->required], $channel->legal),
            'expectedGross' => $quote->total->gross,
            'valid' => $quote->valid,
            'key' => bin2hex(random_bytes(16)),
            'values' => $flash['values'] ?? [],
            'errors' => $flash['errors'] ?? [],
        ];
    }
}
