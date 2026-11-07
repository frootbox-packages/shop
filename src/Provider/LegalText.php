<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

/**
 * A legal text a customer agrees to when ordering, e.g. the terms.
 */
final readonly class LegalText
{
    public function __construct(
        public string $key,
        public string $title,
        public string $version,
        public string $url,
        public bool $required,
    ) {}
}
