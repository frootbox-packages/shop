<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Cart;

use Frootbox\Shop\Catalog\Product;
use Frootbox\Shop\Catalog\Products;
use Frootbox\Shop\Provider\Lines;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Shop;
use Frootbox\Shop\ShopSession;

/**
 * The cart for actions, queries and blocks.
 */
final readonly class Carts
{
    public function __construct(
        private ShopSession $session,
        private Products $products,
        private Shop $shop,
    ) {}

    /**
     * The product of an id of the mirror or of the provider.
     */
    public function product(string $id): ?Product
    {
        return $this->products->fetchById($id) ?? $this->products->byProviderId($id);
    }

    /**
     * Puts pieces of a product into the cart, adding to those there.
     */
    public function add(Product $product, int $quantity): int
    {
        $lines = $this->session->cart();
        $lines[$product->providerId] = $product->quantity(($lines[$product->providerId] ?? 0) + $quantity);
        $this->session->setCart($lines);

        return $lines[$product->providerId];
    }

    /**
     * Sets the pieces of a product; 0 removes it.
     */
    public function set(Product $product, int $quantity): void
    {
        $lines = $this->session->cart();

        if ($quantity <= 0) {
            unset($lines[$product->providerId]);
        } else {
            $lines[$product->providerId] = $product->quantity($quantity);
        }

        $this->session->setCart($lines);
    }

    public function clear(): void
    {
        $this->session->setCart([]);
    }

    /**
     * The cart with the products of the mirror; with $prices and a
     * signed-in customer also the quote.
     */
    public function view(bool $prices = true, ?string $shippingMethod = null, ?string $deliveryAddressId = null): CartView
    {
        $lines = $this->session->cart();
        $products = $this->products->byProviderIds(array_map(strval(...), array_keys($lines)));
        // A product gone from the catalog leaves the cart.
        $lines = array_intersect_key($lines, $products);
        $signedIn = null !== $this->session->customer();
        $quote = $prices && $signedIn && [] !== $lines
            ? $this->shop->forCustomer('frootbox/shop/cart.show', static fn(ShopProvider $provider, string $token) => $provider->quote($token, new Lines($lines), $deliveryAddressId, $shippingMethod))
            : null;
        $priced = [];

        foreach ($quote->lines ?? [] as $line) {
            $priced[$line->productId] = $line;
        }

        $view = [];

        foreach ($lines as $providerId => $quantity) {
            $product = $products[$providerId];
            $line = $priced[$providerId] ?? null;
            $view[] = new CartLine($product->id, $product->providerId, $product->title, $product->sku, $product->slug, $product->media()[0] ?? null, $quantity, $product->minQuantity, $product->quantityStep, $product->orderable, $line?->unit, $line?->total, $line->problems ?? []);
        }

        return new CartView($view, array_sum($lines), $quote, $signedIn);
    }

    /**
     * Pieces in the cart, without asking the provider.
     */
    public function pieces(): int
    {
        return array_sum($this->session->cart());
    }
}
