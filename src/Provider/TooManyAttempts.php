<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

use Throwable;

/**
 * Too many attempts, e.g. to sign in; again after some seconds.
 */
final class TooManyAttempts extends ShopException
{
    public function __construct(public readonly int $retryAfter = 60, ?Throwable $previous = null)
    {
        parent::__construct('Too many attempts.', 429, $previous);
    }
}
