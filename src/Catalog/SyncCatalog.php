<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Url\AddressSync;
use Frootbox\Shop\Provider\Providers;

/**
 * @implements Action<SyncResult>
 */
#[AsAction(CatalogSync::ACTION, 'Brings products, categories and images of the shop up to date with the provider; with full the whole catalog again', undoable: false)]
final readonly class SyncCatalog implements Action
{
    public function __construct(
        #[Description('The whole catalog instead of the changes since the last sync')]
        public bool $full = false,
    ) {}

    public function handle(Providers $providers, CatalogSync $sync, AddressSync $addresses): SyncResult
    {
        $result = $sync->run($providers->get(), $this->full);

        if ($result->changed()) {
            // The pages of products and categories follow (decisions 0232, 0243).
            $addresses->sync();
        }

        return $result;
    }
}
