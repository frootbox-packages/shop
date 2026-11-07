<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Database\Repository;

/**
 * @extends Repository<Link>
 */
final class Links extends Repository
{
    public const string TABLE = 'frootbox_shop_links';

    public function find(string $kind, string $externalId): ?Link
    {
        return $this->fetchOne(['where' => ['kind' => $kind, 'externalId' => $externalId]]);
    }

    /**
     * @return array<string, Link> by external id
     */
    public function all(string $kind): array
    {
        $links = [];

        foreach ($this->fetch(['where' => ['kind' => $kind]]) as $link) {
            $links[$link->externalId] = $link;
        }

        return $links;
    }

    /**
     * Keeps which local id stands for an external one.
     */
    public function put(string $kind, string $externalId, string $localId, ?string $version = null): void
    {
        $link = $this->find($kind, $externalId);

        if (null === $link) {
            $this->insert(['kind' => $kind, 'externalId' => $externalId, 'localId' => $localId, 'version' => $version]);
        } elseif ($link->localId !== $localId || $link->version !== $version) {
            $this->update($link->id, ['localId' => $localId, 'version' => $version]);
        }
    }

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return Link::class;
    }
}
