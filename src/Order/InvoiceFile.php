<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Order;

/**
 * The PDF of an invoice, base64 for the REST API.
 */
final readonly class InvoiceFile
{
    public function __construct(
        public string $fileName,
        public string $content,
    ) {}
}
