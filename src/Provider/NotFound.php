<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * The provider knows no such thing, e.g. an order of another customer.
 */
final class NotFound extends ShopException {}
