<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop;

use DateTimeImmutable;
use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Security\Secrets;
use Frootbox\Core\Visitor\VisitorSession;
use Frootbox\Shop\Customer\SessionCustomer;
use Frootbox\Shop\Provider\CustomerLogin;

/**
 * The shop in the session of the visitor (decisions 0311, 0312): the
 * cart with pieces by product id of the provider, and the signed-in
 * customer with the token encrypted.
 */
final readonly class ShopSession
{
    public const string PACKAGE = 'frootbox/shop';

    private const string PURPOSE = 'frootbox/shop/customer';

    public function __construct(
        private VisitorSession $session,
        private Secrets $secrets,
    ) {}

    /**
     * Visitors act only from a browser; the REST API and MCP get 409.
     */
    public function require(): void
    {
        if (!$this->session->available()) {
            throw new ConflictException('This works only for a visitor of the website in a browser.');
        }
    }

    public function customer(): ?SessionCustomer
    {
        $data = $this->data();

        return null === $data ? null : new SessionCustomer($data['id'], $data['name'], $data['email'], $data['number'], $data['company']);
    }

    public function token(): ?string
    {
        $data = $this->data();

        return null === $data ? null : $this->secrets->decrypt(self::PURPOSE, $data['token']);
    }

    public function signIn(CustomerLogin $login): void
    {
        $this->require();
        // A new cookie, so one seen before signing in counts no more.
        $this->session->regenerate();
        $this->session->set(self::PACKAGE, 'customer', [
            'token' => $this->secrets->encrypt(self::PURPOSE, $login->token),
            'expiresAt' => $login->expiresAt->getTimestamp(),
            'id' => $login->customer->id,
            'name' => $login->customer->name,
            'email' => $login->customer->email,
            'number' => $login->customer->number,
            'company' => $login->customer->company,
        ]);
    }

    /**
     * Signs the customer out; the cart stays.
     */
    public function signOut(): void
    {
        if (null === $this->session->get(self::PACKAGE, 'customer')) {
            return;
        }

        $this->session->remove(self::PACKAGE, 'customer');
        $this->session->regenerate();
    }

    /**
     * @return array<int|string, int> pieces by product id of the provider
     */
    public function cart(): array
    {
        $cart = $this->session->get(self::PACKAGE, 'cart');
        $lines = [];

        foreach (\is_array($cart) ? $cart : [] as $id => $quantity) {
            if (\is_int($quantity) && $quantity > 0) {
                $lines[$id] = $quantity;
            }
        }

        return $lines;
    }

    /**
     * @param array<int|string, int> $lines
     */
    public function setCart(array $lines): void
    {
        $lines = array_filter($lines, static fn(int $quantity): bool => $quantity > 0);

        if ([] === $lines) {
            $this->session->remove(self::PACKAGE, 'cart');

            return;
        }

        $this->require();
        $this->session->set(self::PACKAGE, 'cart', $lines);
    }

    /**
     * A message for the next page, e.g. the errors of a form without
     * JavaScript.
     *
     * @param array<string, string> $errors
     * @param array<string, string> $values
     */
    public function flash(string $form, string $message, array $errors = [], array $values = [], bool $success = false): void
    {
        if ($this->session->available()) {
            $this->session->set(self::PACKAGE, 'flash', ['form' => $form, 'message' => $message, 'errors' => $errors, 'values' => $values, 'success' => $success]);
        }
    }

    /**
     * The message for a form, once.
     *
     * @return array{form: string, message: string, errors: array<string, string>, values: array<string, string>, success: bool}|null
     */
    public function takeFlash(string $form): ?array
    {
        $flash = $this->session->get(self::PACKAGE, 'flash');

        if (!\is_array($flash) || ($flash['form'] ?? null) !== $form) {
            return null;
        }

        $this->session->remove(self::PACKAGE, 'flash');

        return [
            'form' => $form,
            'message' => \is_string($flash['message'] ?? null) ? $flash['message'] : '',
            'errors' => self::strings($flash['errors'] ?? []),
            'values' => self::strings($flash['values'] ?? []),
            'success' => true === ($flash['success'] ?? false),
        ];
    }

    /**
     * @return array{token: string, expiresAt: int, id: string, name: string, email: string, number: string, company: string|null}|null
     */
    private function data(): ?array
    {
        $data = $this->session->get(self::PACKAGE, 'customer');

        if (!\is_array($data) || !\is_string($data['token'] ?? null) || !\is_int($data['expiresAt'] ?? null) || $data['expiresAt'] <= new DateTimeImmutable()->getTimestamp()) {
            return null;
        }

        $text = static fn(string $key): string => \is_string($data[$key] ?? null) ? $data[$key] : '';

        return [
            'token' => $data['token'],
            'expiresAt' => $data['expiresAt'],
            'id' => $text('id'),
            'name' => $text('name'),
            'email' => $text('email'),
            'number' => $text('number'),
            'company' => \is_string($data['company'] ?? null) ? $data['company'] : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function strings(mixed $values): array
    {
        $strings = [];

        foreach (\is_array($values) ? $values : [] as $key => $value) {
            if (\is_string($value)) {
                $strings[(string) $key] = $value;
            }
        }

        return $strings;
    }
}
