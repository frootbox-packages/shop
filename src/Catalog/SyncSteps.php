<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\Runner;

/**
 * Runs steps of the sync one after the other, each its own action and so
 * its own short transaction (decision 0324). Each step takes SyncLock,
 * so another process, e.g. the browser on the screen of the sync, may
 * go on with the same sync in between.
 */
final readonly class SyncSteps
{
    public function __construct(private Runner $runner) {}

    /**
     * Steps until the sync is done or $seconds are over, the result of
     * all together; null if another process kept the lock from the start.
     *
     * @phpstan-impure
     */
    public function run(bool $full, float $seconds): ?SyncResult
    {
        $end = microtime(true) + $seconds;
        $result = null;

        do {
            try {
                $step = $this->runner->run(new SyncCatalog(null === $result && $full, (int) max(0, min(CatalogSync::STEP_SECONDS, ceil($end - microtime(true))))));
            } catch (ConflictException) {
                // Another process syncs and goes on itself.
                return $result;
            }

            $result = null === $result ? $step : $result->plus($step);
        } while (!$result->done && microtime(true) < $end);

        return $result;
    }
}
