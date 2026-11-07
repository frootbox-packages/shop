<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Queue\AsJob;
use Frootbox\Core\Queue\Job;
use Frootbox\Core\Queue\Queue;

/**
 * Syncs the catalog in the background, after a webhook or every five
 * minutes (decision 0311): steps for up to SECONDS, then the next job
 * goes on (decision 0324). Without a cron that is the next request or
 * tick of the hub.
 */
#[AsJob('frootbox/shop/catalog.sync', 'Syncs the catalog of the shop', attempts: 3)]
final readonly class SyncJob implements Job
{
    public const int SECONDS = 20;

    public function handle(SyncSteps $steps, Queue $queue): void
    {
        $result = $steps->run(false, self::SECONDS);

        // Null: another process syncs right now and goes on itself.
        if (null !== $result && !$result->done) {
            $queue->pushOnce(new self());
        }
    }
}
