<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * The position of the last sync is too old; the sync starts anew.
 */
final class StalePosition extends ShopException {}
