<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Core\Language\Languages;
use Frootbox\Core\Plugin\Offer;
use Frootbox\Core\Plugin\Plugin;
use Frootbox\Core\Plugin\Space;

/**
 * The catalog as a plugin (decisions 0232, 0311): the page that shows it
 * is the base of the addresses, categories lie as a path below it,
 * products flat with their slug, because a product can be in several
 * categories.
 */
final readonly class ProductPlugin implements Plugin
{
    public const string BLOCK = 'frootbox/shop/Catalog';

    public function __construct(
        private Products $products,
        private Languages $languages,
    ) {}

    public function type(): string
    {
        return Products::TYPE;
    }

    public function label(): string
    {
        return 'Product catalog';
    }

    public function block(): string
    {
        return self::BLOCK;
    }

    public function details(): Offer
    {
        return Offer::Required;
    }

    public function categories(): Offer
    {
        return Offer::Required;
    }

    public function spaces(): bool
    {
        return false;
    }

    public function paths(string $space): iterable
    {
        $codes = $this->languages->codes();

        foreach ($this->products->fetch(['order' => ['slug']]) as $product) {
            yield $product->id => array_fill_keys($codes, $product->slug);
        }
    }

    public function space(string $id): ?string
    {
        return null === $this->products->fetchById($id) ? null : Space::DEFAULT;
    }

    public function count(string $space): int
    {
        return Space::DEFAULT === $space ? $this->products->count() : 0;
    }

    public function title(string $id, string $language): ?string
    {
        return $this->products->fetchById($id)?->title;
    }

    public function description(string $id, string $language): ?string
    {
        return $this->products->fetchById($id)?->shortDescription;
    }

    public function image(string $id, string $language): ?array
    {
        $product = $this->products->fetchById($id);
        $media = $product?->media()[0] ?? null;

        return null === $product || null === $media ? null : ['media' => $media, 'alt' => $product->title];
    }
}
