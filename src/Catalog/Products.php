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

    /**
     * The products whose title, item number or short text contain every
     * word of a search, ignoring case, by title.
     *
     * @return list<Product>
     */
    public function matching(string $search): array
    {
        $parts = preg_split('/\s+/u', mb_strtolower(trim($search)));
        $words = false === $parts ? [] : array_values(array_filter($parts, static fn(string $word): bool => '' !== $word));

        if ([] === $words) {
            return [];
        }

        $query = $this->connection->createQueryBuilder()->select('*')->from($this->connection->quoteSingleIdentifier(self::TABLE));

        foreach (\array_slice($words, 0, 8) as $index => $word) {
            // "!" escapes the same way in SQLite, MySQL and MariaDB.
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word) . '%';
            $query->andWhere("LOWER(title) LIKE :w{$index} ESCAPE '!' OR LOWER(sku) LIKE :w{$index} ESCAPE '!' OR LOWER(COALESCE(short_description, '')) LIKE :w{$index} ESCAPE '!'")
                ->setParameter('w' . $index, $pattern);
        }

        $query->orderBy('title')->addOrderBy('id');

        return array_map($this->hydrate(...), $query->executeQuery()->fetchAllAssociative());
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
