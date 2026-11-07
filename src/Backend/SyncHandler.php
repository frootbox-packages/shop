<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Backend;

use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\Runner;
use Frootbox\Shop\Catalog\ShowSyncStatus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The screen "Shop sync" (decision 0326): where the sync of the catalog
 * stands, and a sync now, step by step in the browser, which needs no
 * cron.
 */
final readonly class SyncHandler implements RequestHandlerInterface
{
    public const string PERMISSION = 'frootbox/shop/catalog.status';

    public function __construct(
        private BackendView $view,
        private Runner $runner,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        return $this->view->render($request, '@frootbox/shop/backend/sync.twig', [
            'status' => $this->runner->ask(new ShowSyncStatus()),
        ]);
    }
}
