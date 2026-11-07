<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Action\BusyException;
use Frootbox\Core\Action\Runner;

/**
 * Runs steps of the sync one after the other, each its own action and so
 * its own short transaction (decision 0324). Each step takes SyncLock,
 * so another process, e.g. the browser on the screen of the sync, may
 * go on with the same sync in between.
 */
final readonly class SyncSteps
{
    public function __construct(private Runner $runner, private SyncLock $lock) {}

    /**
     * Steps until the sync is done or $seconds are over, the result of
     * all together; null if another process kept the lock from the start.
     * In the background the steps stop while someone syncs in the browser
     * (decision 0331): one step after the other would keep the database
     * from the browser until its step gives up.
     *
     * @phpstan-impure
     */
    public function run(bool $full, float $seconds, bool $background = false): ?SyncResult
    {
        $end = microtime(true) + $seconds;
        $result = null;
        $this->lock->background = $background;

        try {
            do {
                if ($background && $this->lock->foregroundActive()) {
                    return $result;
                }

                try {
                    $step = $this->runner->run(new SyncCatalog(null === $result && $full, (int) max(0, min(CatalogSync::STEP_SECONDS, ceil($end - microtime(true))))));
                } catch (BusyException) {
                    // Another process syncs and goes on itself.
                    return $result;
                }

                $result = null === $result ? $step : $result->plus($step);
            } while (!$result->done && microtime(true) < $end);
        } finally {
            $this->lock->background = false;
        }

        return $result;
    }
}
