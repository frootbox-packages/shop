<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Order;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Shop;

/**
 * @implements Query<InvoiceFile>
 */
#[AsQuery('frootbox/shop/invoice.pdf', 'The PDF of an invoice of the signed-in customer of the shop, base64', public: true)]
final readonly class ShowInvoicePdf implements Query
{
    public function __construct(
        #[Length(min: 1, max: 100)]
        public string $id,
    ) {}

    public function handle(Shop $shop): InvoiceFile
    {
        $pdf = $shop->forCustomer('frootbox/shop/invoice.pdf', fn(ShopProvider $provider, string $token): string => $provider->invoicePdf($token, $this->id));

        return new InvoiceFile('invoice-' . preg_replace('/[^A-Za-z0-9-]/', '', $this->id) . '.pdf', base64_encode($pdf));
    }
}
