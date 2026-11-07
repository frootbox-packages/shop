<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Website;

use NumberFormatter;

/**
 * Amounts for visitors, e.g. "12,45 €" in German.
 */
final class Money
{
    public static function cents(int $cents, string $currency, string $language): string
    {
        return self::format($cents / 100, $currency, $language);
    }

    /**
     * A unit price with four places, shown with two unless more are needed.
     */
    public static function decimal(string $amount, string $currency, string $language): string
    {
        return self::format((float) $amount, $currency, $language);
    }

    private static function format(float $amount, string $currency, string $language): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatted = new NumberFormatter($language, NumberFormatter::CURRENCY)->formatCurrency($amount, $currency);

            if (false !== $formatted) {
                return $formatted;
            }
        }

        $german = !\in_array(substr($language, 0, 2), ['en'], true);
        $number = number_format($amount, 2, $german ? ',' : '.', $german ? '.' : ',');
        $symbol = 'EUR' === $currency ? '€' : $currency;

        return $german ? $number . ' ' . $symbol : $symbol . ' ' . $number;
    }
}
