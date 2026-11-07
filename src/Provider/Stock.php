<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * How available a product is, roughly.
 */
enum Stock: string
{
    case Available = 'available';
    case Low = 'low';
    case OnOrder = 'on-order';
    case Out = 'out';
    case Unknown = 'unknown';
}
