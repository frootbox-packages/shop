<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use DateTimeImmutable;
use Frootbox\Database\Entity;

/**
 * Which own category or medium stands for one of the provider.
 */
final readonly class Link extends Entity
{
    public const string CATEGORY = 'category';

    public const string IMAGE = 'image';

    public function __construct(
        public string $id,
        public string $kind,
        public string $externalId,
        public string $localId,
        public ?string $version = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}
}
