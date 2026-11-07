<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * Whether an invoice is paid; each provider maps its own states.
 */
enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
