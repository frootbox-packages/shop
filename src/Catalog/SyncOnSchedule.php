<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Action\Context;
use Frootbox\Core\Action\CurrentContext;
use Frootbox\Core\ProjectPaths;
use Frootbox\Core\Queue\Queue;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Shop\Provider\Providers;

/**
 * Queues a sync of the catalog every five minutes after a run of the
 * scheduler, also without a cron through the hub (decision 0293).
 */
final readonly class SyncOnSchedule
{
    public const int EVERY = 300;

    public function __construct(
        private Providers $providers,
        private Queue $queue,
        private CurrentContext $context,
        private ProjectPaths $paths,
    ) {}

    public function __invoke(ScheduleRan $event): void
    {
        $marker = $this->paths->var('schedule/shop-synced');

        if (null === $this->providers->active() || (is_file($marker) && $event->now->getTimestamp() - (int) filemtime($marker) < self::EVERY - 10)) {
            return;
        }

        $this->context->runAs(Context::system(), fn(): string => $this->queue->push(new SyncJob()));

        if (!is_dir(\dirname($marker))) {
            @mkdir(\dirname($marker), 0o775, true);
        }

        @touch($marker, $event->now->getTimestamp());
    }
}
