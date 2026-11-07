<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * The cart cannot be ordered as it is, e.g. below the minimum order.
 */
final class QuoteInvalid extends ShopException {}
