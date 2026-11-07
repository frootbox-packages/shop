<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Action\Context;
use Frootbox\Core\Action\CurrentContext;
use Frootbox\Core\Queue\Queue;
use Frootbox\Shop\Provider\Providers;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /_shop/webhook: the provider says the catalog changed. Only queues
 * a sync, so the answer comes at once (decision 0311).
 */
final readonly class WebhookHandler implements RequestHandlerInterface
{
    public const string ROUTE = '/_shop/webhook';

    public function __construct(
        private Providers $providers,
        private Queue $queue,
        private CurrentContext $context,
        private ResponseFactoryInterface $responses,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $provider = $this->providers->active();
        $body = (string) $request->getBody();
        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', $values);
        }

        if (null === $provider || !$provider->verifyWebhook($headers, $body)) {
            return $this->responses->createResponse(401);
        }

        $this->context->runAs(Context::system(), fn(): ?string => $this->queue->pushOnce(new SyncJob()));

        return $this->responses->createResponse(202);
    }
}
