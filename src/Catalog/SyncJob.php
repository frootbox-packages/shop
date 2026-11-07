<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Action\Runner;
use Frootbox\Core\Queue\AsJob;
use Frootbox\Core\Queue\Job;

/**
 * Syncs the catalog in the background, after a webhook or every five
 * minutes (decision 0311).
 */
#[AsJob('frootbox/shop/catalog.sync', 'Syncs the catalog of the shop', attempts: 3)]
final readonly class SyncJob implements Job
{
    public function handle(Runner $runner): void
    {
        $runner->run(new SyncCatalog());
    }
}
