<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use DateTimeImmutable;
use Frootbox\Database\Ulid;

/**
 * Where a sync of the catalog going on stands between its steps
 * (decision 0324): the page of changes it reads and which of them are
 * done. The changes on a page can move while the sync waits, so it
 * notes them, not how many.
 */
final class SyncRun
{
    /**
     * @param string|null  $since  the position it started from, null for the whole catalog
     * @param string|null  $cursor the page it reads, null for the first one
     * @param list<string> $done   the changes on that page that are done
     */
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly ?string $since,
        public readonly string $startedAt,
        public ?string $cursor = null,
        public array $done = [],
        public ?string $until = null,
        /** The categories are up to date in this run */
        public bool $categories = false,
        /** A step changed something */
        public bool $changed = false,
        /** What the steps so far did, for the screen of the sync */
        public int $products = 0,
        public int $deleted = 0,
        public int $categoriesChanged = 0,
        public int $images = 0,
        public int $imageErrors = 0,
        public ?string $imageError = null,
    ) {}

    public static function start(string $source, ?string $since): self
    {
        return new self(Ulid::generate(), $source, $since, new DateTimeImmutable()->format(DATE_ATOM), until: $since);
    }

    /**
     * @param array<string, mixed>|null $values
     */
    public static function from(?array $values): ?self
    {
        if (null === $values || !\is_string($values['id'] ?? null) || !\is_string($values['source'] ?? null)) {
            return null;
        }

        $text = static fn(string $key): ?string => \is_string($values[$key] ?? null) ? $values[$key] : null;
        $number = static fn(string $key): int => \is_int($values[$key] ?? null) ? $values[$key] : 0;
        $done = \is_array($values['done'] ?? null) ? array_values(array_filter($values['done'], \is_string(...))) : [];

        return new self(
            $values['id'],
            $values['source'],
            $text('since'),
            $text('startedAt') ?? '',
            $text('cursor'),
            $done,
            $text('until'),
            true === ($values['categories'] ?? null),
            true === ($values['changed'] ?? null),
            $number('products'),
            $number('deleted'),
            $number('categoriesChanged'),
            $number('images'),
            $number('imageErrors'),
            $text('imageError'),
        );
    }

    public function full(): bool
    {
        return null === $this->since;
    }

    /**
     * Adds what a step did.
     */
    public function add(Counts $step): void
    {
        $this->products += $step->products;
        $this->deleted += $step->deleted;
        $this->categoriesChanged += $step->categories;
        $this->images += $step->images;
        $this->imageErrors += $step->imageErrors;
        $this->imageError = $step->imageError ?? $this->imageError;
    }

    /**
     * What the whole run did, kept after it ended.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'full' => $this->full(),
            'startedAt' => $this->startedAt,
            'endedAt' => new DateTimeImmutable()->format(DATE_ATOM),
            'products' => $this->products,
            'deleted' => $this->deleted,
            'categories' => $this->categoriesChanged,
            'images' => $this->images,
            'imageErrors' => $this->imageErrors,
            'imageError' => $this->imageError,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'since' => $this->since,
            'startedAt' => $this->startedAt,
            'cursor' => $this->cursor,
            'done' => $this->done,
            'until' => $this->until,
            'categories' => $this->categories,
            'changed' => $this->changed,
            'products' => $this->products,
            'deleted' => $this->deleted,
            'categoriesChanged' => $this->categoriesChanged,
            'images' => $this->images,
            'imageErrors' => $this->imageErrors,
            'imageError' => $this->imageError,
        ];
    }
}
