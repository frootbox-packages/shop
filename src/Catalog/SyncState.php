<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use DateTimeImmutable;
use Frootbox\Database\Entity;

/**
 * Where the last sync of the catalog stopped, and in which catalog of
 * the provider (ShopProvider::source(), hashed); with $run where the
 * one going on stands (SyncRun, decision 0324).
 */
final readonly class SyncState extends Entity
{
    public const string CATALOG = 'catalog';

    public function __construct(
        public string $id,
        public ?string $position = null,
        public ?DateTimeImmutable $ranAt = null,
        public ?string $error = null,
        public ?string $source = null,
        /** @var array<string, mixed>|null */
        public ?array $run = null,
        /** @var array<string, mixed>|null What the last sync that ended did (SyncRun::summary()) */
        public ?array $last = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}
}
