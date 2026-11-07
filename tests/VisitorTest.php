<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Tests;

use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\I18n\Formality;
use Frootbox\Shop\Cart\AddToCart;
use Frootbox\Shop\Catalog\Products;
use Frootbox\Shop\Catalog\SyncCatalog;
use Frootbox\Shop\Customer\SignIn;
use Frootbox\Shop\Order\PlaceOrder;
use Frootbox\Shop\Tests\Support\FakeShop;
use Frootbox\Shop\Website\FormHandler;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FormHandler::class)]
#[CoversClass(SignIn::class)]
#[CoversClass(AddToCart::class)]
#[CoversClass(PlaceOrder::class)]
final class VisitorTest extends ShopTestCase
{
    private string $product = '';

    protected function setUp(): void
    {
        parent::setUp();

        FakeShop::$products = ['11' => FakeShop::item('11', 'Echinocactus grusonii')];
        $this->runner()->run(new SyncCatalog());
        $this->product = (string) $this->get(Products::class)->byProviderId('11')?->id;

        $start = $this->page(null, 'Start');
        $this->catalogPage($start);
        $this->page($start, 'Anmeldung', [['block' => 'frootbox/shop/SignIn']]);
        $this->page($start, 'Warenkorb', [['block' => 'frootbox/shop/Cart']]);
        $this->page($start, 'Kasse', [['block' => 'frootbox/shop/Checkout']]);
        $this->page($start, 'Konto', [['block' => 'frootbox/shop/Account']]);
    }

    public function testAGuestSeesNoPricesAndGetsNoCookie(): void
    {
        $response = $this->browse('/shop/echinocactus-grusonii');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $response->getHeader('Set-Cookie'));
        self::assertStringNotContainsString('10,00', (string) $response->getBody());
        self::assertStringContainsString('href="/anmeldung?return=', (string) $response->getBody());
    }

    public function testAGuestFillsTheCartAndSignsInForPrices(): void
    {
        $added = $this->browse('/_shop/cart-add', ['product' => $this->product, 'quantity' => '6', '_return' => '/shop/echinocactus-grusonii']);
        self::assertSame(303, $added->getStatusCode());
        self::assertSame('/shop/echinocactus-grusonii', $added->getHeaderLine('Location'));
        self::assertNotNull($this->cookie);

        $product = $this->html('/shop/echinocactus-grusonii');
        self::assertStringContainsString('Das Produkt liegt im Warenkorb.', $product);

        $cart = $this->html('/warenkorb');
        self::assertStringContainsString('Echinocactus grusonii', $cart);
        self::assertStringContainsString('value="6"', $cart);

        // A wrong password: back to the form with a message, no customer.
        $wrong = $this->browse('/_shop/sign-in', ['email' => FakeShop::EMAIL, 'password' => 'falsch', '_return' => '/anmeldung']);
        self::assertSame('/anmeldung', $wrong->getHeaderLine('Location'));
        $form = $this->html('/anmeldung');
        self::assertStringContainsString('Bitte prüfe Deine Angaben.', $form);
        self::assertStringContainsString('value="' . FakeShop::EMAIL . '"', $form);
        self::assertStringNotContainsString('falsch', $form);

        $cookie = $this->cookie;
        $signedIn = $this->browse('/_shop/sign-in', ['email' => FakeShop::EMAIL, 'password' => FakeShop::PASSWORD, '_return' => '/anmeldung']);
        self::assertSame('/konto', $signedIn->getHeaderLine('Location'));
        // A new cookie after signing in, the cart stays.
        self::assertNotSame($cookie, $this->cookie);
        self::assertStringContainsString('value="6"', $this->html('/warenkorb'));

        $product = $this->browse('/shop/echinocactus-grusonii');
        self::assertStringContainsString('10,00', (string) $product->getBody());
        self::assertStringContainsString('no-store', $product->getHeaderLine('Cache-Control'));
    }

    public function testACustomerOrdersOnceAndSeesOrdersAndInvoices(): void
    {
        $this->signIn();
        $this->browse('/_shop/cart-add', ['product' => $this->product, 'quantity' => '6', '_return' => '/warenkorb']);

        $checkout = $this->html('/kasse');
        self::assertSame(1, preg_match('/name="key" value="([^"]+)"/', $checkout, $key), $checkout);
        self::assertSame(1, preg_match('/name="expectedGross" value="(\d+)"/', $checkout, $gross));
        $order = ['key' => $key[1], 'expectedGross' => $gross[1], 'shippingMethod' => 'standard', 'paymentMethod' => 'invoice', 'deliveryAddressId' => 'd1', 'reference' => 'Frühjahr', '_return' => '/kasse'];

        // A text without consent is only a notice.
        self::assertStringContainsString('Bitte beachte auch: <a href="https://example.org/datenschutz"', $checkout);
        self::assertStringNotContainsString('value="privacy"', $checkout);

        // Without the terms nothing is ordered.
        $this->browse('/_shop/order', $order);
        self::assertCount(0, FakeShop::placed());
        self::assertStringContainsString('Bitte prüfe Deine Angaben.', $this->html('/kasse'));

        $placed = $this->browse('/_shop/order', [...$order, 'consents' => ['terms', 'privacy']]);
        self::assertSame('/kasse', $placed->getHeaderLine('Location'));
        self::assertStringContainsString('Vielen Dank für Deine Bestellung B-1000.', $this->html('/kasse'));
        $sent = FakeShop::placed()[0] ?? null;
        self::assertCount(1, FakeShop::placed());
        self::assertNotNull($sent);
        self::assertSame('Frühjahr', $sent->reference);
        self::assertSame([['key' => 'terms', 'version' => '3']], $sent->consents);

        $account = $this->html('/konto');
        self::assertStringContainsString('B-1000', $account);
        // The provider sends the time as UTC with "Z"; it shows as a date.
        self::assertStringContainsString('<td>07.11.2026</td>', $account);
        self::assertStringContainsString('<td>Eingegangen</td>', $account);
        self::assertStringContainsString('<td>Offen</td>', $account);
        self::assertStringContainsString('R-2026-1', $account);
        // The cart is empty after the order.
        self::assertStringNotContainsString('value="6"', $this->html('/warenkorb'));

        $pdf = $this->browse('/_shop/invoice/i1');
        self::assertSame(200, $pdf->getStatusCode());
        self::assertSame('application/pdf', $pdf->getHeaderLine('Content-Type'));
        self::assertSame('%PDF-1.4 fake', (string) $pdf->getBody());
    }

    public function testATotalThatChangedIsNotOrdered(): void
    {
        $this->signIn();
        $this->browse('/_shop/cart-add', ['product' => $this->product, 'quantity' => '6', '_return' => '/warenkorb']);

        $this->browse('/_shop/order', ['key' => 'checkout-1', 'expectedGross' => '1', 'shippingMethod' => 'standard', 'paymentMethod' => 'invoice', 'consents' => ['terms'], '_return' => '/kasse']);

        self::assertCount(0, FakeShop::placed());
        self::assertStringContainsString('Die Summe hat sich inzwischen geändert.', $this->html('/kasse'));
    }

    public function testSigningOutEndsTheLoginAndKeepsTheCart(): void
    {
        $this->signIn();
        $this->browse('/_shop/cart-add', ['product' => $this->product, 'quantity' => '12', '_return' => '/warenkorb']);

        $this->browse('/_shop/sign-out', ['_return' => '/warenkorb']);

        self::assertSame(['avc_1'], FakeShop::$revoked);
        $cart = $this->html('/warenkorb');
        self::assertStringContainsString('value="12"', $cart);
        self::assertStringNotContainsString('120,00', $cart);
        // An invoice now leads to the sign-in.
        $invoice = $this->browse('/_shop/invoice/i1');
        self::assertSame(303, $invoice->getStatusCode());
        self::assertSame('/anmeldung?return=%2F_shop%2Finvoice%2Fi1', $invoice->getHeaderLine('Location'));
    }

    public function testASignInThatTheShopNoLongerKnowsEnds(): void
    {
        $this->signIn();
        FakeShop::$revoked[] = 'avc_1';

        $account = $this->browse('/konto');

        self::assertStringNotContainsString('R-2026-1', (string) $account->getBody());
    }

    public function testFormsComeOnlyFromThisWebsite(): void
    {
        $response = $this->browse('/_shop/cart-add', ['product' => $this->product], ['Origin' => 'https://evil.example']);

        self::assertSame(403, $response->getStatusCode());
        self::assertNull($this->cookie);
    }

    public function testWithoutASessionTheActionsOfVisitorsRefuse(): void
    {
        $this->expectException(ConflictException::class);

        $this->runner()->run(new AddToCart($this->product, 6));
    }

    public function testTextsAddressTheCustomerAsTheShopSays(): void
    {
        FakeShop::$formality = Formality::Formal;
        $this->signIn();

        $account = $this->html('/konto');
        self::assertStringContainsString('Ihre Daten', $account);
        self::assertStringNotContainsString('Deine Daten', $account);

        // Signed out, the website addresses the visitor as it does for all.
        $this->browse('/_shop/sign-out', ['_return' => '/konto']);
        self::assertStringContainsString('Bitte melde Dich an, um Dein Konto zu sehen.', $this->html('/konto'));
    }

    private function signIn(): void
    {
        $this->browse('/_shop/sign-in', ['email' => FakeShop::EMAIL, 'password' => FakeShop::PASSWORD, '_return' => '/anmeldung']);
        self::assertNotNull($this->cookie);
    }
}
