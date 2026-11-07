<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use DateTimeImmutable;
use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Queue\Jobs;
use Frootbox\Shop\Provider\Providers;
use Frootbox\Shop\Provider\ShopProviderEntry;

/**
 * @implements Query<SyncStatus>
 */
#[AsQuery('frootbox/shop/catalog.status', 'Where the sync of the catalog stands: the one going on, the last one, products without image and jobs of the queue')]
final readonly class ShowSyncStatus implements Query
{
    public function handle(Providers $providers, SyncStates $states, Products $products, Jobs $jobs): SyncStatus
    {
        $provider = $providers->active();
        $entry = null === $provider ? null : array_find($providers->entries(), static fn(ShopProviderEntry $entry): bool => $entry->class === $provider::class);
        $state = $states->catalog();
        $run = SyncRun::from($state->run);
        $running = null;

        if (null !== $run) {
            $running = $run->summary();
            unset($running['endedAt']);
            $running['seen'] = $run->full() ? $products->count(['syncedIn' => $run->id]) : null;
        }

        $failed = null;

        foreach ($jobs->all() as $job) {
            if (SyncJob::NAME === $job->name && null !== $job->failedAt && (null === $failed || $job->failedAt > $failed->failedAt)) {
                $failed = $job;
            }
        }

        return new SyncStatus(
            null === $provider ? null : ($entry->title ?? $provider::class),
            $running,
            $state->last,
            null === $state->last ? null : $state->ranAt,
            $products->count(),
            $products->count(['images' => '[]']),
            $jobs->waiting(SyncJob::NAME, new DateTimeImmutable()),
            $failed?->lastError,
            $failed?->failedAt,
        );
    }
}
