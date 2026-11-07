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
        /** What writes the copied images, e.g. "GD 2.3.3" (decision 0334) */
        public string $imageDriver = '',
        /** @var array{seconds: float, perProduct: float|null, shares: array<string, int>}|null Where the time of the sync going on, else the last one, went */
        public ?array $timing = null,
    ) {}

    /**
     * Where the time of a run went: its seconds, those per product asked
     * for and the share of each kind in percent, with rest for the work in
     * the database; null for a run before the measuring.
     *
     * @param array<string, mixed>|null $summary SyncRun::summary()
     *
     * @return array{seconds: float, perProduct: float|null, shares: array<string, int>}|null
     */
    public static function timing(?array $summary): ?array
    {
        $seconds = \is_array($summary['seconds'] ?? null) ? $summary['seconds'] : [];
        $total = $seconds['total'] ?? null;

        if (!\is_float($total) && !\is_int($total) || $total <= 0) {
            return null;
        }

        $shares = [];
        $rest = 100;

        foreach (['shop', 'download', 'images'] as $key) {
            $part = $seconds[$key] ?? 0;
            $shares[$key] = \is_float($part) || \is_int($part) ? (int) round(min(1, $part / $total) * 100) : 0;
            $rest -= $shares[$key];
        }

        $shares['rest'] = max(0, $rest);
        $checked = \is_int($summary['checked'] ?? null) ? $summary['checked'] : 0;

        return ['seconds' => (float) $total, 'perProduct' => $checked > 0 ? round($total / $checked, 1) : null, 'shares' => $shares];
    }
}
