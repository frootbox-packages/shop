<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * The shop of the website at the provider.
 */
final readonly class Channel
{
    /**
     * @param list<Choice>    $paymentMethods
     * @param list<LegalText> $legal
     * @param bool            $grossFirst     Whether prices show gross first; B2B shops show net
     * @param bool            $catalogPublic  Whether visitors see the catalog without signing in; prices always need it
     * @param int|null        $minimumNet     Net value of goods an order must reach, in cents
     */
    public function __construct(
        public string $name,
        public string $currency,
        public bool $grossFirst = false,
        public bool $catalogPublic = true,
        public array $paymentMethods = [],
        public ?int $minimumNet = null,
        public array $legal = [],
    ) {}
}
