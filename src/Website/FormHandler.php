<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Website;

use Frootbox\Core\Action\AccessDeniedException;
use Frootbox\Core\Action\ActionException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\Http\Exception\HttpException;
use Frootbox\Core\I18n\Translator;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Support\Message;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Shop\Provider\OrderSummary;
use Frootbox\Shop\ShopSession;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The forms of the shop on the website at POST /_shop/<form> (decision
 * 0311). Each passes its fields to a public action and leads back to the
 * page (303), with a message in the session for the next page; they work
 * without JavaScript. Only from pages of this website (Origin).
 */
final readonly class FormHandler implements RequestHandlerInterface
{
    public const string ROUTE = '/_shop/{form:[a-z-]+}';

    /** The forms and their actions with the fields they take. */
    private const array FORMS = [
        'sign-in' => ['frootbox/shop/customer.sign-in', ['email', 'password']],
        'sign-out' => ['frootbox/shop/customer.sign-out', []],
        'request-reset' => ['frootbox/shop/customer.request-reset', ['email']],
        'reset-password' => ['frootbox/shop/customer.reset-password', ['email', 'token', 'password']],
        'change-password' => ['frootbox/shop/customer.change-password', ['currentPassword', 'password']],
        'cart-add' => ['frootbox/shop/cart.add', ['product', 'quantity']],
        'cart-update' => ['frootbox/shop/cart.update', ['quantities']],
        'order' => ['frootbox/shop/order.place', ['shippingMethod', 'paymentMethod', 'expectedGross', 'key', 'deliveryAddressId', 'reference', 'comment', 'consents']],
    ];

    /** Fields never kept for the next page. */
    private const array SECRET = ['password', 'currentPassword', 'token'];

    public function __construct(
        private Runner $runner,
        private ShopSession $session,
        private ShopPages $pages,
        private Translator $translator,
        private Languages $languages,
        private ResponseFactoryInterface $responses,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $form = $request->getAttribute('form');

        if (!\is_string($form) || !isset(self::FORMS[$form])) {
            throw HttpException::notFound($request->getUri()->getPath());
        }

        if (!self::sameOrigin($request)) {
            throw new HttpException(403, 'Forms of the shop come only from pages of this website.');
        }

        $body = $request->getParsedBody();
        $body = \is_array($body) ? $body : [];
        [$action, $fields] = self::FORMS[$form];
        $language = \is_string($body['_language'] ?? null) && $this->languages->has($body['_language']) ? $body['_language'] : $this->languages->default()->code;
        $back = self::back(\is_string($body['_return'] ?? null) ? $body['_return'] : null);
        $input = $this->input($form, $fields, $body, $request, $back);

        try {
            $result = $this->runner->call($action, $input);
        } catch (InvalidInputException $error) {
            $errors = [];

            foreach ($error->messages as $pointer => $message) {
                $errors[ltrim($pointer, '/')] = $this->translate($language, $message);
            }

            $this->session->flash($form, $this->translate($language, new Message('Please check your entries.')), $errors, self::kept($input));

            return $this->redirect($back);
        } catch (AccessDeniedException $error) {
            if (!$error->signInRequired) {
                throw $error;
            }

            $this->session->flash('sign-in', $this->translate($language, $error->template));
            $signIn = $this->pages->href(ShopPages::SIGN_IN, $language);

            return $this->redirect(null === $signIn ? $back : $signIn . '?' . http_build_query(['return' => $back]));
        } catch (ActionException|EntityNotFoundException $error) {
            $this->session->flash($form, $this->translate($language, $error instanceof ActionException ? $error->template : new Message('This is no longer there.')), [], self::kept($input));

            return $this->redirect($back);
        }

        return $this->redirect($this->success($form, $result, $language, $back, $body));
    }

    /**
     * The input of the action from the fields of the form.
     *
     * @param list<string>         $fields
     * @param array<mixed>         $body
     *
     * @return array<string, mixed>
     */
    private function input(string $form, array $fields, array $body, ServerRequestInterface $request, string $back): array
    {
        $input = [];

        foreach ($fields as $field) {
            $value = $body[$field] ?? null;

            if ('consents' === $field || 'quantities' === $field) {
                $input[$field] = \is_array($value) ? $value : [];
            } elseif ('quantity' === $field || 'expectedGross' === $field) {
                $input[$field] = is_numeric($value) ? (int) $value : ('quantity' === $field ? 1 : 0);
            } elseif (\is_string($value) && '' !== $value) {
                $input[$field] = $value;
            }
        }

        if ('request-reset' === $form) {
            $uri = $request->getUri();
            $input['returnUrl'] = $uri->getScheme() . '://' . $uri->getAuthority() . (string) parse_url($back, \PHP_URL_PATH);
        }

        return $input;
    }

    /**
     * Where to go after the action, with a message for the page.
     *
     * @param array<mixed> $body
     */
    private function success(string $form, mixed $result, string $language, string $back, array $body): string
    {
        $message = match ($form) {
            'request-reset' => new Message('If the address belongs to a customer, a link to set a new password is on its way.'),
            'reset-password' => new Message('Your new password is set. You can sign in with it now.'),
            'change-password' => new Message('Your password has been changed.'),
            'cart-add' => new Message('The product is in the cart.'),
            'order' => $result instanceof OrderSummary ? new Message('Thank you for your order {number}.', ['number' => $result->number]) : new Message('Thank you for your order.'),
            default => null,
        };

        if (null !== $message) {
            $this->session->flash($form, $this->translate($language, $message), success: true);
        }

        $next = \is_string($body['_success'] ?? null) ? self::back($body['_success']) : null;

        return match ($form) {
            // The link from the mail carries email and token; they are done now.
            'reset-password' => (string) parse_url($back, \PHP_URL_PATH),
            'sign-in' => $next ?? $this->pages->href(ShopPages::ACCOUNT, $language) ?? $back,
            default => $next ?? $back,
        };
    }

    private function translate(string $language, Message $message): string
    {
        return $this->translator->in($language, $message->text, ['frootbox/shop'], $message->parameters);
    }

    private function redirect(string $location): ResponseInterface
    {
        return $this->responses->createResponse(303)->withHeader('Location', $location)->withHeader('Cache-Control', 'no-store');
    }

    /**
     * The values to show again in the form, without secrets.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, string>
     */
    private static function kept(array $input): array
    {
        $kept = [];

        foreach ($input as $field => $value) {
            if (!\in_array($field, self::SECRET, true) && (\is_string($value) || \is_int($value))) {
                $kept[$field] = (string) $value;
            }
        }

        return $kept;
    }

    /**
     * Whether the form was sent from a page of this website: the header
     * Origin, else Referer, names this host.
     */
    private static function sameOrigin(ServerRequestInterface $request): bool
    {
        $uri = $request->getUri();
        $own = strtolower($uri->getScheme() . '://' . $uri->getAuthority());
        $origin = $request->getHeaderLine('Origin');

        if ('' !== $origin) {
            return strtolower(rtrim($origin, '/')) === $own;
        }

        $referer = $request->getHeaderLine('Referer');

        return '' === $referer || str_starts_with(strtolower($referer), $own . '/');
    }

    /**
     * The path to lead back to: one on this website, else the start.
     */
    private static function back(?string $return): string
    {
        return null !== $return && 1 === preg_match('~^/(?![/\\\\])~', $return) && 0 === preg_match('~[\x00-\x1f]~', $return) ? $return : '/';
    }
}
