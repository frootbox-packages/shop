<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Provider;

use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Extension\Registry;
use Frootbox\Shop\ShopSettings;
use Psr\Container\ContainerInterface;

/**
 * The providers extensions registered and the active one (decision
 * 0311): the one of the setting "provider", or the only one.
 */
final readonly class Providers
{
    public function __construct(
        private Registry $registry,
        private ShopSettings $settings,
        private ContainerInterface $container,
    ) {}

    /**
     * @return list<ShopProviderEntry>
     */
    public function entries(): array
    {
        return $this->registry->items(ShopProviderEntry::class);
    }

    public function active(): ?ShopProvider
    {
        $entries = $this->entries();
        $entry = '' === $this->settings->provider
            ? (1 === \count($entries) ? $entries[0] : null)
            : array_find($entries, fn(ShopProviderEntry $entry): bool => $entry->class === $this->settings->provider);

        if (null === $entry) {
            return null;
        }

        $provider = $this->container->get($entry->class);

        return $provider instanceof ShopProvider ? $provider : null;
    }

    public function get(): ShopProvider
    {
        return $this->active() ?? throw new ConflictException('No shop is connected. Install a provider such as avaro/shop, or choose one in the settings of the shop.');
    }
}
