<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use DateTimeImmutable;
use Frootbox\Database\Entity;

/**
 * Where the last sync of the catalog stopped.
 */
final readonly class SyncState extends Entity
{
    public const string CATALOG = 'catalog';

    public function __construct(
        public string $id,
        public ?string $position = null,
        public ?DateTimeImmutable $ranAt = null,
        public ?string $error = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}
}
