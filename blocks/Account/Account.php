<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Blocks;

use DateTimeInterface;
use Frootbox\Core\Action\AccessDeniedException;
use Frootbox\Core\Action\ActionException;
use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Url\Urls;
use Frootbox\Core\View\Delivery;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Shop\Provider\Address;
use Frootbox\Shop\Provider\Invoice;
use Frootbox\Shop\Provider\OrderLine;
use Frootbox\Shop\Provider\OrderSummary;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Shop;
use Frootbox\Shop\ShopSession;
use Frootbox\Shop\Website\ShopPages;
use Frootbox\Shop\Website\ShopRendering;
use IntlDateFormatter;

/**
 * The account of the signed-in customer: data and addresses, orders with
 * one in detail, invoices as PDF, the password and signing out.
 */
final readonly class Account implements BlockController
{
    public function __construct(
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
        $language = $context->language;
        $here = null === $context->target ? '/' : $this->urls->href($context->target, $language) ?? '/';
        $signIn = $this->pages->href(ShopPages::SIGN_IN, $language);
        $base = [
            'flash' => $this->session->takeFlash('change-password'),
            'hidden' => ShopRendering::hidden($here, $language),
            'signIn' => null === $signIn ? null : $signIn . '?' . http_build_query(['return' => $here]),
            'here' => $here,
        ];

        if (null === $this->session->customer()) {
            return ['state' => 'signed-out', ...$base];
        }

        try {
            $customer = $this->shop->forCustomer('frootbox/shop/customer.show', static fn(ShopProvider $provider, string $token) => $provider->customer($token));
            $orders = $this->shop->forCustomer('frootbox/shop/order.list', static fn(ShopProvider $provider, string $token) => $provider->orders($token));
            $invoices = $this->shop->forCustomer('frootbox/shop/invoice.list', static fn(ShopProvider $provider, string $token) => $provider->invoices($token));
            $chosen = $context->query('order');
            $order = null === $chosen || '' === $chosen ? null : $this->shop->forCustomer('frootbox/shop/order.show', static fn(ShopProvider $provider, string $token) => $provider->order($token, $chosen));
        } catch (AccessDeniedException) {
            return ['state' => 'signed-out', ...$base];
        } catch (EntityNotFoundException) {
            $order = null;
            $customer ??= null;
        } catch (ActionException $error) {
            return ['state' => 'error', 'error' => (string) $error->template, ...$base];
        }

        if (!isset($customer, $orders, $invoices)) {
            return ['state' => 'error', 'error' => 'This is no longer there.', ...$base];
        }

        $date = static fn(DateTimeInterface $date): string => (string) IntlDateFormatter::formatObject($date, [IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE], $language);
        $summary = fn(OrderSummary $item): array => ['id' => $item->id, 'number' => $item->number, 'status' => $item->status, 'date' => $date($item->createdAt), 'reference' => $item->reference, 'total' => $this->rendering->amount($item->total, $item->currency, $language)['gross'], 'href' => $here . '?' . http_build_query(['order' => $item->id])];

        return [
            'state' => 'ready',
            ...$base,
            'customer' => ['name' => $customer->name, 'email' => $customer->email, 'number' => $customer->number, 'company' => $customer->company, 'vatId' => $customer->vatId, 'billing' => $customer->billingAddress?->lines() ?? []],
            'addresses' => array_map(static fn(Address $address): array => $address->lines(), $customer->deliveryAddresses),
            'orders' => array_map($summary, $orders->items),
            'order' => null === $order ? null : [
                ...$summary($order->summary),
                'lines' => array_map(fn(OrderLine $line): array => ['title' => $line->title, 'sku' => $line->sku, 'quantity' => $line->quantity, 'total' => $this->rendering->amount($line->total, $order->summary->currency, $language)['net']], $order->lines),
                'goods' => $this->rendering->amount($order->goods, $order->summary->currency, $language),
                'shipping' => $this->rendering->amount($order->shipping, $order->summary->currency, $language),
                'grand' => $this->rendering->amount($order->summary->total, $order->summary->currency, $language),
                'delivery' => $order->deliveryAddress->lines(),
            ],
            'invoices' => array_map(fn(Invoice $invoice): array => ['number' => $invoice->number, 'kind' => $invoice->kind, 'status' => $invoice->status, 'date' => $date($invoice->date), 'total' => $this->rendering->amount($invoice->total, $invoice->currency, $language)['gross'], 'href' => '/_shop/invoice/' . rawurlencode($invoice->id)], $invoices->items),
        ];
    }
}
