<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\ProjectPaths;
use RuntimeException;

/**
 * Only one step of the sync at a time (decision 0324): a step reads where
 * the last one stopped, which counts only once that one is committed. A
 * file in var/, so it holds for every process on this server.
 */
final class SyncLock
{
    /** A browser that syncs sends its next step within this, even after a retry. */
    public const int FOREGROUND_SECONDS = 30;

    /** How long a step waits for another one to end, by default. */
    public float $wait = 15;

    /** True while SyncSteps runs in the background, e.g. as a job. */
    public bool $background = false;

    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly ProjectPaths $paths) {}

    /**
     * Waits up to $seconds for the lock; false if another process keeps it.
     */
    public function acquire(?float $seconds = null): bool
    {
        $seconds ??= $this->wait;

        if (null !== $this->handle) {
            return true;
        }

        $file = $this->paths->var('shop/sync.lock');

        if (!is_dir(\dirname($file))) {
            @mkdir(\dirname($file), 0o775, true);
        }

        $handle = @fopen($file, 'c');

        if (false === $handle) {
            throw new RuntimeException(\sprintf('The lock file %s cannot be opened.', $file));
        }

        $end = microtime(true) + $seconds;

        while (!flock($handle, \LOCK_EX | \LOCK_NB)) {
            if (microtime(true) >= $end) {
                fclose($handle);

                return false;
            }

            usleep(200_000);
        }

        $this->handle = $handle;

        return true;
    }

    /**
     * Notes that a step runs outside the background, e.g. in the browser
     * on the screen of the sync (decision 0331).
     */
    public function markForeground(): void
    {
        $file = $this->paths->var('shop/sync.foreground');

        if (!is_dir(\dirname($file))) {
            @mkdir(\dirname($file), 0o775, true);
        }

        @touch($file);
        clearstatcache(true, $file);
    }

    /**
     * Whether a step outside the background ran in the last $seconds: the
     * background then steps back, else its steps one after the other keep
     * the database from the browser until it gives up.
     */
    public function foregroundActive(int $seconds = self::FOREGROUND_SECONDS): bool
    {
        $file = $this->paths->var('shop/sync.foreground');
        clearstatcache(true, $file);
        $time = @filemtime($file);

        return false !== $time && time() - $time < $seconds;
    }

    public function release(): void
    {
        if (null !== $this->handle) {
            flock($this->handle, \LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
