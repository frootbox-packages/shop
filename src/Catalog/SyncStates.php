<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use DateTimeImmutable;
use Frootbox\Database\Repository;

/**
 * @extends Repository<SyncState>
 */
final class SyncStates extends Repository
{
    public const string TABLE = 'frootbox_shop_sync';

    public function catalog(): SyncState
    {
        return $this->fetchById(SyncState::CATALOG) ?? new SyncState(SyncState::CATALOG);
    }

    /**
     * A sync that is done: where the next one starts.
     */
    /**
     * @param array<string, mixed>|null $last what the sync did
     */
    public function save(?string $position, DateTimeImmutable $ranAt, ?string $error, ?string $source = null, ?array $last = null): void
    {
        $this->put(['position' => $position, 'ranAt' => $ranAt, 'error' => null === $error ? null : mb_substr($error, 0, 2000), 'source' => $source, 'run' => null, 'last' => $last]);
    }

    /**
     * Where the sync going on stands after a step.
     */
    public function progress(SyncRun $run): void
    {
        $this->put(['run' => $run->toArray()]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function put(array $values): void
    {
        if (null === $this->fetchById(SyncState::CATALOG)) {
            $this->insert(['id' => SyncState::CATALOG, ...$values]);
        } else {
            $this->update(SyncState::CATALOG, $values);
        }
    }

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return SyncState::class;
    }
}
