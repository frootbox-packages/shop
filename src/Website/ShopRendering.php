<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Website;

use Frootbox\Core\Action\ActionException;
use Frootbox\Core\Field\Field;
use Frootbox\Core\Field\FieldTypes;
use Frootbox\Core\Url\Target;
use Frootbox\Core\Url\Urls;
use Frootbox\Shop\Cart\CartLine;
use Frootbox\Shop\Cart\CartView;
use Frootbox\Shop\Catalog\Product;
use Frootbox\Shop\Catalog\Products;
use Frootbox\Shop\Provider\Amount;
use Frootbox\Shop\Provider\InvoiceStatus;
use Frootbox\Shop\Provider\OrderStatus;
use Frootbox\Shop\Provider\Price;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Provider\Stock;
use Frootbox\Shop\Provider\UnitPrice;
use Frootbox\Shop\Shop;
use Frootbox\Shop\ShopSession;
use Twig\Markup;

/**
 * What the blocks of the shop show of products, prices and sums.
 */
final readonly class ShopRendering
{
    public const string CARD_SIZES = '(min-width: 62rem) 25vw, (min-width: 36rem) 50vw, 100vw';

    public const string DETAIL_SIZES = '(min-width: 62rem) 50vw, 100vw';

    public function __construct(
        private FieldTypes $types,
        private Shop $shop,
        private ShopSession $session,
        private Urls $urls,
    ) {}

    /**
     * An image of the media library as HTML.
     */
    public function image(?string $media, string $alt, string $sizes, ?string $ratio = null, bool $eager = false): Markup|string
    {
        if (null === $media) {
            return '';
        }

        $output = $this->types->get('image')->output(['media' => $media, 'alt' => $alt], new Field('image', 'image', 'Image', settings: array_filter(['ratio' => $ratio, 'sizes' => $sizes, 'eager' => $eager], static fn(mixed $value): bool => null !== $value)));

        return $output instanceof Markup ? $output : (\is_string($output) ? $output : '');
    }

    /**
     * The prices of products for the signed-in customer; none for a
     * guest or while the shop does not answer.
     *
     * @param list<Product> $products
     *
     * @return array<string, Price> by provider id
     */
    public function prices(array $products): array
    {
        if (null === $this->session->customer() || [] === $products) {
            return [];
        }

        try {
            return $this->shop->forCustomer('frootbox/shop/cart.show', static fn(ShopProvider $provider, string $token): array => $provider->prices($token, array_map(static fn(Product $product): string => $product->providerId, $products)));
        } catch (ActionException) {
            return [];
        }
    }

    /**
     * A price for the view: net first for B2B, with the other one and
     * the tiers.
     *
     * @return array{main: string, other: string, regular: string|null, discount: string|null, tiers: list<array{from: int, price: string}>, base: string|null}
     */
    public function price(Price $price, string $language, bool $grossFirst = false): array
    {
        $main = $grossFirst ? $price->unit->gross : $price->unit->net;
        $other = $grossFirst ? $price->unit->net : $price->unit->gross;
        $regular = null === $price->regularUnit ? null : ($grossFirst ? $price->regularUnit->gross : $price->regularUnit->net);

        return [
            'main' => Money::decimal($main, $price->currency, $language),
            'other' => Money::decimal($other, $price->currency, $language),
            'regular' => null === $regular ? null : Money::decimal($regular, $price->currency, $language),
            'discount' => $price->discountPercentage,
            'tiers' => array_map(static fn(array $tier): array => ['from' => $tier['minQuantity'], 'price' => Money::decimal($grossFirst ? $tier['unit']->gross : $tier['unit']->net, $price->currency, $language)], $price->tiers),
            'base' => null === $price->basePrice ? null : Money::decimal($price->basePrice['gross'], $price->currency, $language) . ' / ' . $price->basePrice['quantity'] . ' ' . $price->basePrice['unit'],
        ];
    }

    public function unit(UnitPrice $unit, string $currency, string $language, bool $grossFirst = false): string
    {
        return Money::decimal($grossFirst ? $unit->gross : $unit->net, $currency, $language);
    }

    /**
     * @return array{net: string, tax: string, gross: string, main: string}
     */
    public function amount(Amount $amount, string $currency, string $language, bool $grossFirst = false): array
    {
        return [
            'net' => Money::cents($amount->net, $currency, $language),
            'tax' => Money::cents($amount->tax, $currency, $language),
            'gross' => Money::cents($amount->gross, $currency, $language),
            'main' => Money::cents($grossFirst ? $amount->gross : $amount->net, $currency, $language),
        ];
    }

    /**
     * The availability for visitors, e.g. "In stock".
     *
     * @return array{key: string, text: string}
     */
    public static function stock(Product $product): array
    {
        $stock = $product->stock();
        $text = match ($stock) {
            Stock::Available => 'In stock',
            Stock::Low => 'Only a few left',
            Stock::OnOrder => 'On order',
            Stock::Out => 'Out of stock',
            Stock::Unknown => '',
        };

        return ['key' => $stock->value, 'text' => $text];
    }

    /**
     * The English text of the state of an order, for t() in a view.
     */
    public static function orderStatus(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Received => 'Received',
            OrderStatus::Processing => 'In progress',
            OrderStatus::Completed => 'Completed',
            OrderStatus::Cancelled => 'Cancelled',
        };
    }

    /**
     * The English text of the state of an invoice, for t() in a view.
     */
    public static function invoiceStatus(InvoiceStatus $status): string
    {
        return match ($status) {
            InvoiceStatus::Open => 'Open',
            InvoiceStatus::Paid => 'Paid',
            InvoiceStatus::Cancelled => 'Cancelled',
        };
    }

    /**
     * Hidden fields every form of the shop sends along.
     *
     * @return array{_return: string, _language: string}
     */
    public static function hidden(string $return, string $language): array
    {
        return ['_return' => $return, '_language' => $language];
    }

    /**
     * The lines and totals of a cart for a view.
     *
     * @return array{lines: list<array<string, mixed>>, pieces: int, signedIn: bool, totals: array<string, mixed>|null}
     */
    public function cart(CartView $view, string $language): array
    {
        $quote = $view->quote;
        $currency = $quote->currency ?? 'EUR';

        return [
            'lines' => array_map(fn(CartLine $line): array => [
                'id' => $line->productId,
                'title' => $line->title,
                'sku' => $line->sku,
                'href' => $this->urls->href(Target::parse(Products::TYPE . ':' . $line->productId), $language),
                'image' => $this->image($line->image, $line->title, '6rem', '1:1'),
                'quantity' => $line->quantity,
                'minQuantity' => $line->minQuantity,
                'quantityStep' => $line->quantityStep,
                'unit' => null === $line->unit ? null : $this->unit($line->unit, $currency, $language),
                'total' => null === $line->total ? null : $this->amount($line->total, $currency, $language)['main'],
                'problems' => $line->problems,
            ], $view->lines),
            'pieces' => $view->pieces,
            'signedIn' => $view->signedIn,
            'totals' => null === $quote ? null : [
                'goods' => $this->amount($quote->goods, $currency, $language),
                'shipping' => $this->amount($quote->shipping, $currency, $language),
                'total' => $this->amount($quote->total, $currency, $language),
                'valid' => $quote->valid,
                'minimumMissing' => $quote->minimumMissing > 0 ? Money::cents($quote->minimumMissing, $currency, $language) : null,
            ],
        ];
    }
}
