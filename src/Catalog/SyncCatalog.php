<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Bookkeeping;
use Frootbox\Core\Action\BusyException;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Shop\Provider\Providers;

/**
 * @implements Action<SyncResult>
 */
#[AsAction(CatalogSync::ACTION, 'Brings products, categories and images of the shop up to date with the provider, one step at a time: until done is true, the next call goes on; with full the whole catalog again', undoable: false)]
final readonly class SyncCatalog implements Action
{
    public function __construct(
        #[Description('The whole catalog instead of the changes since the last sync; starts anew')]
        public bool $full = false,
        #[Description('How long the step may take, about; at least one product is done')]
        #[Range(min: 0, max: 60)]
        public int $seconds = CatalogSync::STEP_SECONDS,
    ) {}

    public function handle(Providers $providers, CatalogSync $sync, SyncLock $lock, Bookkeeping $afterwards): SyncResult
    {
        if (!$lock->background) {
            $lock->markForeground();
        }

        if (!$lock->acquire()) {
            throw new BusyException('Another sync of the catalog is running right now. Try again in a moment.');
        }

        // Only after the commit, so the next step reads where this one stopped.
        $afterwards->afterwards($lock->release(...));

        return $sync->run($providers->get(), $this->full, $this->seconds);
    }
}
