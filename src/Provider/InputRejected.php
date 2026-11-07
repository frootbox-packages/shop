<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

use Throwable;

/**
 * The provider rejected the input, with a message per field, e.g.
 * "/password" for a wrong password or "/email".
 */
final class InputRejected extends ShopException
{
    /**
     * @param array<string, string> $errors message by pointer
     */
    public function __construct(public readonly array $errors, ?Throwable $previous = null)
    {
        parent::__construct(implode(' ', $errors), 422, $previous);
    }
}
