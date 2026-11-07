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

    public function save(?string $position, DateTimeImmutable $ranAt, ?string $error, ?string $source = null): void
    {
        $values = ['position' => $position, 'ranAt' => $ranAt, 'error' => null === $error ? null : mb_substr($error, 0, 2000), 'source' => $source];

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
