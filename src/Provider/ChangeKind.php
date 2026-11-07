<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

enum ChangeKind: string
{
    case Product = 'product';
    case Category = 'category';
}
