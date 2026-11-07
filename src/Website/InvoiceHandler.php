<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Website;

use Frootbox\Core\Action\AccessDeniedException;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\Http\Exception\HttpException;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Shop\Order\ShowInvoicePdf;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /_shop/invoice/<id>: the PDF of an invoice of the signed-in
 * customer, for this visitor only.
 */
final readonly class InvoiceHandler implements RequestHandlerInterface
{
    public const string ROUTE = '/_shop/invoice/{id:[A-Za-z0-9-]{1,100}}';

    public function __construct(
        private Runner $runner,
        private ShopPages $pages,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $path = $request->getUri()->getPath();

        if (!\is_string($id)) {
            throw HttpException::notFound($path);
        }

        try {
            $file = $this->runner->ask(new ShowInvoicePdf($id));
        } catch (AccessDeniedException) {
            $signIn = $this->pages->href(ShopPages::SIGN_IN);

            return $this->responses->createResponse(303)->withHeader('Location', null === $signIn ? '/' : $signIn . '?' . http_build_query(['return' => $path]))->withHeader('Cache-Control', 'no-store');
        } catch (EntityNotFoundException) {
            throw HttpException::notFound($path);
        }

        return $this->responses->createResponse(200)
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $file->fileName . '"')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Robots-Tag', 'noindex')
            ->withBody($this->streams->createStream((string) base64_decode($file->content, true)));
    }
}
