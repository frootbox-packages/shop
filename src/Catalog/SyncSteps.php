<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Action\Runner;
use Frootbox\Core\ProjectPaths;

/**
 * Runs steps of the sync one after the other, each its own action and so
 * its own short transaction (decision 0324). Only one process at a time:
 * a step reads where the last one stopped, which counts only once that
 * one is committed.
 */
final readonly class SyncSteps
{
    public function __construct(
        private Runner $runner,
        private ProjectPaths $paths,
    ) {}

    /**
     * Steps until the sync is done or $seconds are over, the result of
     * all together; null if another process syncs right now.
     *
     * @phpstan-impure
     */
    public function run(bool $full, float $seconds): ?SyncResult
    {
        $file = $this->paths->var('shop/sync.lock');

        if (!is_dir(\dirname($file))) {
            @mkdir(\dirname($file), 0o775, true);
        }

        $lock = @fopen($file, 'c');

        if (false === $lock) {
            throw new \RuntimeException(\sprintf('The lock file %s cannot be opened.', $file));
        }

        try {
            if (!flock($lock, \LOCK_EX | \LOCK_NB)) {
                return null;
            }

            $end = microtime(true) + $seconds;
            $result = null;

            do {
                $step = $this->runner->run(new SyncCatalog(null === $result && $full, (int) max(0, min(CatalogSync::STEP_SECONDS, ceil($end - microtime(true))))));
                $result = null === $result ? $step : $result->plus($step);
            } while (!$result->done && microtime(true) < $end);

            return $result;
        } finally {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }
}
