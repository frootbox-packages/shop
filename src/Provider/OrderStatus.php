<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * Where an order stands, roughly; each provider maps its own states.
 */
enum OrderStatus: string
{
    /** Placed, not yet taken on by the shop. */
    case Received = 'received';

    /** Taken on, being prepared or delivered. */
    case Processing = 'processing';

    /** Delivered or otherwise finished. */
    case Completed = 'completed';

    case Cancelled = 'cancelled';
}
