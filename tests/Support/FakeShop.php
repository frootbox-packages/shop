<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Tests\Support;

use DateTimeImmutable;
use Frootbox\Core\I18n\Formality;
use Frootbox\Shop\Provider\Address;
use Frootbox\Shop\Provider\Amount;
use Frootbox\Shop\Provider\CatalogChange;
use Frootbox\Shop\Provider\ChangeKind;
use Frootbox\Shop\Provider\ChangePage;
use Frootbox\Shop\Provider\Channel;
use Frootbox\Shop\Provider\Choice;
use Frootbox\Shop\Provider\Customer;
use Frootbox\Shop\Provider\CustomerLogin;
use Frootbox\Shop\Provider\InputRejected;
use Frootbox\Shop\Provider\Invoice;
use Frootbox\Shop\Provider\InvoiceStatus;
use Frootbox\Shop\Provider\LegalText;
use Frootbox\Shop\Provider\Lines;
use Frootbox\Shop\Provider\Listing;
use Frootbox\Shop\Provider\NotFound;
use Frootbox\Shop\Provider\Order;
use Frootbox\Shop\Provider\OrderLine;
use Frootbox\Shop\Provider\OrderRequest;
use Frootbox\Shop\Provider\OrderStatus;
use Frootbox\Shop\Provider\OrderSummary;
use Frootbox\Shop\Provider\PostalAddress;
use Frootbox\Shop\Provider\Price;
use Frootbox\Shop\Provider\Quote;
use Frootbox\Shop\Provider\QuoteLine;
use Frootbox\Shop\Provider\ShippingOption;
use Frootbox\Shop\Provider\ShopCategory;
use Frootbox\Shop\Provider\ShopImage;
use Frootbox\Shop\Provider\ShopProduct;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Provider\ShopUnavailable;
use Frootbox\Shop\Provider\SignedOut;
use Frootbox\Shop\Provider\StalePosition;
use Frootbox\Shop\Provider\TotalChanged;
use Frootbox\Shop\Provider\UnitPrice;

/**
 * A shop in memory for the tests. Prices are 10.00 net per piece, 19 %
 * VAT, shipping 5.00 net.
 */
final class FakeShop implements ShopProvider
{
    public const string EMAIL = 'kunde@example.test';

    public const string PASSWORD = 'geheim-123';


    /** @var array<int|string, ShopProduct> by id; PHP keeps numeric ids as int */
    public static array $products = [];

    /** @var list<ShopCategory> */
    public static array $categories = [];

    /** @var list<CatalogChange> changes since position "1" */
    public static array $changes = [];

    /** Whether a position is too old. */
    public static bool $stale = false;

    /** @var array<string, OrderSummary> by idempotency key */
    public static array $orders = [];

    /** @var list<OrderRequest> */
    public static array $placed = [];

    /** @var list<string> */
    public static array $downloads = [];

    /** @var list<string> */
    public static array $resets = [];

    /** @var list<string> tokens no longer valid */
    public static array $revoked = [];

    public static int $signIns = 0;

    /** The catalog the positions belong to, e.g. another installation of the shop */
    public static string $source = 'https://shop.example client';

    /** How the customer wants to be addressed */
    public static ?Formality $formality = null;

    public static function reset(): void
    {
        self::$formality = null;
        self::$products = [];
        self::$categories = [];
        self::$changes = [];
        self::$stale = false;
        self::$orders = [];
        self::$placed = [];
        self::$downloads = [];
        self::$resets = [];
        self::$revoked = [];
        self::$signIns = 0;
        self::$source = 'https://shop.example client';
    }

    /**
     * The orders sent to the shop so far; changes with each order.
     *
     * @return list<OrderRequest>
     *
     * @phpstan-impure
     */
    public static function placed(): array
    {
        return self::$placed;
    }

    public function channel(): Channel
    {
        return new Channel('Kakteen', 'EUR', false, true, [new Choice('invoice', 'Rechnung')], 5000, [new LegalText('terms', 'AGB', '3', 'https://example.org/agb', true), new LegalText('privacy', 'Datenschutzerklärung', '1', 'https://example.org/datenschutz', false)]);
    }

    public function source(): string
    {
        return self::$source;
    }

    public function changes(?string $since, ?string $cursor = null): ChangePage
    {
        if (null === $since) {
            $all = [...array_map(static fn(ShopCategory $category): CatalogChange => new CatalogChange(ChangeKind::Category, $category->id), self::$categories), ...array_map(static fn(string $id): CatalogChange => new CatalogChange(ChangeKind::Product, $id), array_map(strval(...), array_keys(self::$products)))];

            // Two pages, the second one says where to go on.
            return null === $cursor ? new ChangePage(\array_slice($all, 0, 2), 'two') : new ChangePage(\array_slice($all, 2), null, '1');
        }

        if (self::$stale) {
            self::$stale = false;

            throw new StalePosition('Too old.');
        }

        return new ChangePage(self::$changes, null, (string) ((int) $since + 1));
    }

    public function categories(): array
    {
        return self::$categories;
    }

    public function product(string $id): ?ShopProduct
    {
        return self::$products[$id] ?? null;
    }

    public function downloadImage(ShopImage $image, string $path): void
    {
        if ('missing' === $image->id) {
            throw new ShopUnavailable('The image is missing.');
        }

        self::$downloads[] = $image->id . '@' . $image->version;
        // One colour per version: a new version is a new file.
        $picture = imagecreatetruecolor(2, 2);
        imagefill($picture, 0, 0, crc32($image->version) & 0xFFFFFF);
        imagepng($picture, $path);
    }

    public function signIn(string $email, string $password): CustomerLogin
    {
        if (self::EMAIL !== $email || self::PASSWORD !== $password) {
            throw new InputRejected(['/password' => 'Email or password are wrong.']);
        }

        ++self::$signIns;

        return new CustomerLogin('avc_' . self::$signIns, new DateTimeImmutable('+30 days'), $this->customer('avc_' . self::$signIns));
    }

    public function signOut(string $token): void
    {
        self::$revoked[] = $token;
    }

    public function customer(string $token): Customer
    {
        $this->check($token);

        return new Customer('c1', 'Erika Muster', self::EMAIL, '10001', 'Gärtnerei Muster', null, new Address('b', 'Erika Muster', 'Gärtnerei Muster', null, 'Gartenweg 1', '12345', 'Blumenstadt', 'DE'), [new Address('d1', 'Lager', null, null, 'Hof 2', '12345', 'Blumenstadt', 'DE')], self::$formality);
    }

    public function changePassword(string $token, string $current, string $new): void
    {
        $this->check($token);

        if (self::PASSWORD !== $current) {
            throw new InputRejected(['/currentPassword' => 'The current password is wrong.']);
        }
    }

    public function requestPasswordReset(string $email, string $returnUrl): void
    {
        self::$resets[] = $email . ' ' . $returnUrl;
    }

    public function resetPassword(string $email, string $token, string $password): void
    {
        if ('good' !== $token) {
            throw new InputRejected(['/token' => 'The link has expired.']);
        }
    }

    public function prices(string $token, array $productIds): array
    {
        $this->check($token);
        $prices = [];

        foreach ($productIds as $id) {
            $prices[$id] = new Price('EUR', new UnitPrice('10.0000', '11.9000', '19.00'), tiers: [['minQuantity' => 12, 'unit' => new UnitPrice('9.0000', '10.7100', '19.00')]]);
        }

        return $prices;
    }

    public function quote(string $token, Lines $lines, ?string $deliveryAddressId = null, ?string $shippingMethod = null): Quote
    {
        $this->check($token);
        $quoteLines = [];
        $net = 0;

        foreach ($lines->quantities as $id => $quantity) {
            $product = self::$products[(string) $id] ?? null;
            $net += $quantity * 1000;
            $quoteLines[] = new QuoteLine((string) $id, $product?->sku, $product?->title, $quantity, new UnitPrice('10.0000', '11.9000', '19.00'), self::amount($quantity * 1000));
        }

        $shipping = self::amount(500);
        $total = self::amount($net + 500);

        return new Quote('EUR', $quoteLines, self::amount($net), [new ShippingOption('standard', 'Spedition', $shipping)], 'standard', $shipping, [new Choice('invoice', 'Rechnung')], $total, $net >= 5000, 5000, max(0, 5000 - $net));
    }

    public function placeOrder(string $token, OrderRequest $order, string $idempotencyKey): OrderSummary
    {
        $this->check($token);

        if (isset(self::$orders[$idempotencyKey])) {
            return self::$orders[$idempotencyKey];
        }

        $quote = $this->quote($token, $order->lines);

        if ($quote->total->gross !== $order->expectedGross) {
            throw new TotalChanged($quote);
        }

        self::$placed[] = $order;

        return self::$orders[$idempotencyKey] = new OrderSummary('o' . \count(self::$orders), 'B-' . (1000 + \count(self::$orders)), OrderStatus::Received, 'EUR', $quote->total, new DateTimeImmutable('2026-11-07T09:00:00Z'), $order->reference);
    }

    public function orders(string $token, ?string $cursor = null): Listing
    {
        $this->check($token);

        return new Listing(array_values(self::$orders));
    }

    public function order(string $token, string $id): Order
    {
        $this->check($token);

        foreach (self::$orders as $summary) {
            if ($summary->id === $id) {
                return new Order($summary, [new OrderLine('Echinocactus', 12, self::amount(12000), 'K-1')], self::amount(12000), self::amount(500), new PostalAddress('Erika Muster'), new PostalAddress('Lager', street: 'Hof 2'));
            }
        }

        throw new NotFound('No such order.');
    }

    public function invoices(string $token, ?string $cursor = null): Listing
    {
        $this->check($token);

        return new Listing([new Invoice('i1', 'R-2026-1', 'Invoice', InvoiceStatus::Open, 'EUR', self::amount(12500), new DateTimeImmutable('2026-11-07'))]);
    }

    public function invoicePdf(string $token, string $id): string
    {
        $this->check($token);

        return 'i1' === $id ? '%PDF-1.4 fake' : throw new NotFound('No such invoice.');
    }

    public function verifyWebhook(array $headers, string $body): bool
    {
        return 'secret' === ($headers['webhook-signature'] ?? null);
    }

    /**
     * @param list<string>    $categories
     * @param list<ShopImage> $images
     * @param list<string>    $tags
     */
    public static function item(string $id, string $title, array $categories = [], array $images = [], array $tags = []): ShopProduct
    {
        return new ShopProduct($id, '', 'K-' . $id, $title, $categories, $images, description: '<p>Ein <b>schöner</b> Kaktus.<script>alert(1)</script></p>', minQuantity: 6, quantityStep: 6, tags: $tags);
    }

    private static function amount(int $net): Amount
    {
        $tax = intdiv($net * 19, 100);

        return new Amount($net, $tax, $net + $tax);
    }

    private function check(string $token): void
    {
        if (!str_starts_with($token, 'avc_') || \in_array($token, self::$revoked, true)) {
            throw new SignedOut('Signed out.');
        }
    }
}
