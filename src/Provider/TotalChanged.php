<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

use Throwable;

/**
 * The total of the order differs from what the customer saw; the new
 * quote comes with it.
 */
final class TotalChanged extends ShopException
{
    public function __construct(public readonly Quote $quote, ?Throwable $previous = null)
    {
        parent::__construct('The total of the order has changed.', 409, $previous);
    }
}
