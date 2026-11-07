<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

use RuntimeException;

/**
 * An error of the shop; the provider throws only these (decision 0311).
 */
abstract class ShopException extends RuntimeException {}
