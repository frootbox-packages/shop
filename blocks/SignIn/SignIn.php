<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Blocks;

use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Url\Urls;
use Frootbox\Core\View\Delivery;
use Frootbox\Shop\ShopSession;
use Frootbox\Shop\Website\ShopPages;
use Frootbox\Shop\Website\ShopRendering;

/**
 * Signing in to the shop, a link for a forgotten password and the form
 * for the new one, which the link of the mail opens with email and
 * token. A signed-in customer sees who they are and can sign out.
 */
final readonly class SignIn implements BlockController
{
    public function __construct(
        private ShopSession $session,
        private ShopPages $pages,
        private Urls $urls,
        private Delivery $delivery,
    ) {}

    public function data(BlockContext $context): array
    {
        $here = null === $context->target ? '/' : $this->urls->href($context->target, $context->language) ?? '/';
        $return = $context->query('return');
        $return = null !== $return && 1 === preg_match('~^/(?![/\\\\])~', $return) ? $return : null;
        $token = $context->query('token');
        $email = $context->query('email');
        $mode = match (true) {
            null !== $token && '' !== $token => 'reset',
            '1' === $context->query('forgotten') => 'forgotten',
            default => 'sign-in',
        };

        if ('reset' === $mode) {
            // The token is for this visitor only.
            $this->delivery->withoutCache();
        }

        $flash = $this->session->takeFlash('sign-in') ?? $this->session->takeFlash('request-reset') ?? $this->session->takeFlash('reset-password') ?? $this->session->takeFlash('sign-out');
        $customer = $this->session->customer();

        return [
            'mode' => $mode,
            'customer' => null === $customer ? null : ['name' => $customer->name, 'email' => $customer->email],
            'account' => $this->pages->href(ShopPages::ACCOUNT, $context->language),
            'flash' => $flash,
            'values' => $flash['values'] ?? [],
            'errors' => $flash['errors'] ?? [],
            'hidden' => ShopRendering::hidden($here . (null === $return ? '' : '?' . http_build_query(['return' => $return])), $context->language),
            'success' => $return,
            'token' => 'reset' === $mode ? $token : null,
            'email' => $email,
            'here' => $here,
        ];
    }
}
