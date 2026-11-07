<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use DateTimeImmutable;

/**
 * Where the sync of the catalog stands (decision 0326).
 */
final readonly class SyncStatus
{
    /**
     * @param array<string, mixed>|null $running what the sync going on did so far (SyncRun::summary() without endedAt), with seen: the products a sync of the whole catalog has seen
     * @param array<string, mixed>|null $last    what the last sync that ended did (SyncRun::summary())
     */
    public function __construct(
        /** The name of the active provider, null without one */
        public ?string $provider,
        public ?array $running,
        public ?array $last,
        public ?DateTimeImmutable $lastEndedAt,
        /** Products in the mirror */
        public int $products,
        /** Of them without an image */
        public int $withoutImage,
        /** A job of the sync waits in the queue */
        public bool $queued,
        /** The error of the last job of the sync that failed for good, if any */
        public ?string $failed,
        public ?DateTimeImmutable $failedAt,
    ) {}
}
