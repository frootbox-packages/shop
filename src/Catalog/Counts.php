<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

/**
 * What one step of a sync did so far.
 *
 * @internal
 */
final class Counts
{
    public int $products = 0;

    public int $deleted = 0;

    public int $categories = 0;

    public int $images = 0;

    public int $removedImages = 0;

    /** Images that could not be copied */
    public int $imageErrors = 0;

    /** Why the last of them could not */
    public ?string $imageError = null;

    /** It asked the provider for categories or a product */
    public bool $work = false;

    /** Products it asked the provider for, changed or not */
    public int $checked = 0;

    /**
     * Seconds of the step (decision 0334): shop for the questions to the
     * provider, download for the images from it, images for preparing
     * and saving them, total for the whole step.
     *
     * @var array<string, float>
     */
    public array $seconds = [];

    private readonly float $started;

    public function __construct()
    {
        $this->started = microtime(true);
    }

    /**
     * Adds the seconds since $since to $key.
     */
    public function time(string $key, float $since): void
    {
        $this->seconds[$key] = ($this->seconds[$key] ?? 0.0) + microtime(true) - $since;
    }

    /**
     * Ends the step: its seconds in all.
     */
    public function stop(): void
    {
        $this->seconds['total'] = microtime(true) - $this->started;
    }

    public function worked(): bool
    {
        return $this->work;
    }

    public function result(bool $full, bool $done): SyncResult
    {
        return new SyncResult($this->products, $this->deleted, $this->categories, $this->images, $full, $this->removedImages, $done);
    }
}
