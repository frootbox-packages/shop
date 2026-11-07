<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop;

use Frootbox\Core\Action\AccessDeniedException;
use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Auth\TooManyAttemptsException;
use Frootbox\Core\Support\Message;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Shop\Provider\InputRejected;
use Frootbox\Shop\Provider\NotFound;
use Frootbox\Shop\Provider\Providers;
use Frootbox\Shop\Provider\QuoteInvalid;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Provider\ShopUnavailable;
use Frootbox\Shop\Provider\SignedOut;
use Frootbox\Shop\Provider\TooManyAttempts;

/**
 * Calls the provider for actions and queries and turns its errors into
 * those of actions: unavailable and invalid quotes 409, a token no longer
 * valid signs the customer out (401), rejected input 422.
 */
final readonly class Shop
{
    public function __construct(
        private Providers $providers,
        private ShopSession $session,
    ) {}

    /**
     * @template T
     *
     * @param callable(ShopProvider): T $call
     *
     * @return T
     */
    public function call(string $action, callable $call): mixed
    {
        try {
            return $call($this->providers->get());
        } catch (InputRejected $error) {
            throw new InvalidInputException($action, $error->errors);
        } catch (TooManyAttempts $error) {
            throw new TooManyAttemptsException($error->retryAfter);
        } catch (QuoteInvalid $error) {
            throw new ConflictException('' === $error->getMessage() ? 'The cart cannot be ordered like this.' : $error->getMessage());
        } catch (NotFound) {
            throw EntityNotFoundException::for('shop', $action);
        } catch (ShopUnavailable) {
            throw new ConflictException('The shop cannot be reached right now. Please try again in a moment.');
        }
    }

    /**
     * For the signed-in customer; without one 401.
     *
     * @template T
     *
     * @param callable(ShopProvider, string): T $call
     *
     * @return T
     */
    public function forCustomer(string $action, callable $call): mixed
    {
        $token = $this->session->token() ?? throw self::signIn();

        try {
            return $this->call($action, static fn(ShopProvider $provider): mixed => $call($provider, $token));
        } catch (SignedOut) {
            $this->session->signOut();

            throw self::signIn();
        }
    }

    public static function signIn(): AccessDeniedException
    {
        return new AccessDeniedException(new Message('Please sign in to the shop.'), true);
    }
}
