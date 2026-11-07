<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Order;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\ListOf;
use Frootbox\Core\Support\Message;
use Frootbox\Shop\Cart\Carts;
use Frootbox\Shop\Provider\Lines;
use Frootbox\Shop\Provider\OrderRequest;
use Frootbox\Shop\Provider\OrderSummary;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Provider\TotalChanged;
use Frootbox\Shop\Shop;
use Frootbox\Shop\ShopSession;

/**
 * @implements Action<OrderSummary>
 */
#[AsAction('frootbox/shop/order.place', 'Orders the cart of the signed-in customer at the shop; the same key again gives the same order', public: true, undoable: false)]
final readonly class PlaceOrder implements Action
{
    public const string NAME = 'frootbox/shop/order.place';

    /**
     * @param list<string> $consents
     */
    public function __construct(
        #[Length(min: 1, max: 100)]
        public string $shippingMethod,
        #[Length(min: 1, max: 100)]
        public string $paymentMethod,
        #[Description('The total gross in cents the customer saw; if it changed meanwhile, nothing is ordered')]
        public int $expectedGross,
        #[Description('A key of the checkout, so sending twice orders once')]
        #[Length(min: 8, max: 100)]
        public string $key,
        #[Length(max: 100)]
        public ?string $deliveryAddressId = null,
        #[Length(max: 100)]
        public ?string $reference = null,
        #[Length(max: 2000)]
        public ?string $comment = null,
        #[Description('Keys of the legal texts the customer agreed to')]
        #[ListOf('string')]
        public array $consents = [],
    ) {}

    public function handle(Shop $shop, Carts $carts, ShopSession $session): OrderSummary
    {
        $session->require();
        $lines = $session->cart();

        if ([] === $lines) {
            throw new ConflictException('The cart is empty.');
        }

        $channel = $shop->call(self::NAME, static fn(ShopProvider $provider) => $provider->channel());
        $consents = [];

        foreach ($channel->legal as $text) {
            if (\in_array($text->key, $this->consents, true)) {
                $consents[] = ['key' => $text->key, 'version' => $text->version];
            } elseif ($text->required) {
                throw new InvalidInputException(self::NAME, ['/consents' => new Message('Please agree to “{title}”.', ['title' => $text->title])]);
            }
        }

        $request = new OrderRequest(
            new Lines($lines),
            $this->shippingMethod,
            $this->paymentMethod,
            $this->expectedGross,
            self::text($this->deliveryAddressId),
            self::text($this->reference),
            self::text($this->comment),
            $consents,
        );

        try {
            $order = $shop->forCustomer(self::NAME, fn(ShopProvider $provider, string $token): OrderSummary => $provider->placeOrder($token, $request, $this->key));
        } catch (TotalChanged) {
            throw new ConflictException('The total has changed meanwhile. Please check the order and send it again.');
        }

        $carts->clear();

        return $order;
    }

    private static function text(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }
}
