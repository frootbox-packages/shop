<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A shop behind the website, e.g. Avaro (decision 0311). It takes and
 * gives the values of this module, never its own, and throws only a
 * ShopException. A provider registers with
 *
 *     $registry->add(ShopProviderEntry::class, new ShopProviderEntry(AcmeShop::class, 'Acme'));
 *
 * Methods with $token act for a signed-in customer; an invalid token
 * throws SignedOut.
 */
interface ShopProvider
{
    public function channel(): Channel;

    /**
     * The changes of the catalog since a position, page by page. Without
     * $since every product and category counts as changed.
     *
     * @throws StalePosition if $since is too old
     */
    public function changes(?string $since, ?string $cursor = null): ChangePage;

    /**
     * All categories, each after the one above it.
     *
     * @return list<ShopCategory>
     */
    public function categories(): array;

    public function product(string $id): ?ShopProduct;

    /**
     * Writes the file of an image to a local path.
     */
    public function downloadImage(ShopImage $image, string $path): void;

    /**
     * @throws InputRejected for a wrong email or password
     */
    public function signIn(string $email, string $password): CustomerLogin;

    public function signOut(string $token): void;

    public function customer(string $token): Customer;

    /**
     * @throws InputRejected e.g. "/currentPassword"
     */
    public function changePassword(string $token, string $current, string $new): void;

    /**
     * Sends a link to reset the password; says nothing about whether the
     * address is known.
     *
     * @param string $returnUrl The page the link leads to, with email and token in the query
     */
    public function requestPasswordReset(string $email, string $returnUrl): void;

    /**
     * @throws InputRejected if the token is wrong or expired, "/token"
     */
    public function resetPassword(string $email, string $token, string $password): void;

    /**
     * The prices of products for a customer.
     *
     * @param list<string> $productIds
     *
     * @return array<string, Price> by product id
     */
    public function prices(string $token, array $productIds): array;

    public function quote(string $token, Lines $lines, ?string $deliveryAddressId = null, ?string $shippingMethod = null): Quote;

    /**
     * Places an order once per key: the same key again gives the same order.
     *
     * @throws TotalChanged
     * @throws QuoteInvalid
     */
    public function placeOrder(string $token, OrderRequest $order, string $idempotencyKey): OrderSummary;

    /**
     * @return Listing<OrderSummary>
     */
    public function orders(string $token, ?string $cursor = null): Listing;

    /**
     * @throws NotFound
     */
    public function order(string $token, string $id): Order;

    /**
     * @return Listing<Invoice>
     */
    public function invoices(string $token, ?string $cursor = null): Listing;

    /**
     * The PDF of an invoice.
     *
     * @throws NotFound
     */
    public function invoicePdf(string $token, string $id): string;

    /**
     * Whether a webhook comes from the provider.
     *
     * @param array<string, string> $headers by lower-case name
     */
    public function verifyWebhook(array $headers, string $body): bool;
}
