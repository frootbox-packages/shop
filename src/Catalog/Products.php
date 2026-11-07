<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use Frootbox\Database\Repository;

/**
 * @extends Repository<Product>
 */
final class Products extends Repository
{
    public const string TABLE = 'frootbox_shop_products';

    /** The type of the targets of products (decision 0233). */
    public const string TYPE = 'frootbox/shop/product';

    /** The group of the categories of the catalog. */
    public const string CATEGORIES = 'frootbox/shop/catalog';

    public function byProviderId(string $id): ?Product
    {
        return $this->fetchOne(['where' => ['providerId' => $id]]);
    }

    public function bySlug(string $slug): ?Product
    {
        return $this->fetchOne(['where' => ['slug' => $slug]]);
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, Product> by provider id
     */
    public function byProviderIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $products = [];

        foreach ($this->fetch(['where' => ['providerId' => array_values(array_unique($ids))]]) as $product) {
            $products[$product->providerId] = $product;
        }

        return $products;
    }

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return Product::class;
    }
}
