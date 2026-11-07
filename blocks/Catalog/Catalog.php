<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Blocks;

use Frootbox\Core\Category\Categories;
use Frootbox\Core\Category\Category;
use Frootbox\Core\Category\CategoryLinks;
use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Plugin\CategoryPages;
use Frootbox\Core\Url\Target;
use Frootbox\Core\Url\Urls;
use Frootbox\RichText\RichText;
use Frootbox\Shop\Catalog\Product;
use Frootbox\Shop\Catalog\Products;
use Frootbox\Shop\Provider\Price;
use Frootbox\Shop\ShopSession;
use Frootbox\Shop\Website\ShopPages;
use Frootbox\Shop\Website\ShopRendering;

/**
 * The view of the shop at plugin() (decision 0311): on the page of the
 * plugin the categories at the top and all products, on the page of a
 * category the ones below it and its products, on the page of a product
 * the product with images, text, availability and for a signed-in
 * customer the price.
 */
final readonly class Catalog implements BlockController
{
    public function __construct(
        private Products $products,
        private Categories $categories,
        private CategoryLinks $links,
        private CategoryPages $pages,
        private Urls $urls,
        private ShopRendering $rendering,
        private ShopSession $session,
        private ShopPages $shopPages,
    ) {}

    public function data(BlockContext $context): array
    {
        $language = $context->contentLanguage;
        $base = null === $context->target ? '' : $this->urls->href($context->target, $context->language) ?? '';
        $subpage = $context->subpage;
        $signedIn = null !== $this->session->customer();
        $signIn = $this->shopPages->href(ShopPages::SIGN_IN, $context->language);
        $common = [
            'signedIn' => $signedIn,
            'signIn' => null === $signIn ? null : $signIn . '?' . http_build_query(['return' => $this->here($context, $base)]),
            'cart' => $this->shopPages->href(ShopPages::CART, $context->language),
            'hidden' => ShopRendering::hidden($this->here($context, $base), $context->language),
            'flash' => $this->session->takeFlash('cart-add'),
        ];

        if (null !== $subpage && Products::TYPE === $subpage->target->type) {
            $product = $this->products->fetchById($subpage->target->id);

            if (null !== $product) {
                return ['product' => $this->detail($product, $context), 'back' => $base, ...$common];
            }
        }

        $own = $this->pages->current($context);
        $category = $own[0] ?? null;
        $children = $this->categories->children(Products::CATEGORIES, $category?->id);
        $perPage = max(1, min(96, is_numeric($context->options['perPage'] ?? null) ? (int) $context->options['perPage'] : 24));
        $all = $this->list(null === $own ? null : $own[1]);
        $total = \count($all);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($context->query('page') ?? 1)));
        $shown = \array_slice($all, ($page - 1) * $perPage, $perPage);
        $prices = $this->rendering->prices($shown);
        $here = null === $own || null === $subpage ? $base : $this->urls->href($subpage->target, $context->language) ?? $base;

        return [
            'category' => null === $category ? null : ['title' => $category->titleIn([$language]), 'description' => $category->description($language)],
            'categories' => array_values(array_filter(array_map(fn(Category $child): ?array => $this->categoryLink($context, $child, $language), $children))),
            'products' => array_map(fn(Product $product): array => $this->card($product, $prices[$product->providerId] ?? null, $context), $shown),
            'total' => $total,
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'previous' => $page > 1 ? $here . ($page > 2 ? '?page=' . ($page - 1) : '') : null,
                'next' => $page < $pages ? $here . '?page=' . ($page + 1) : null,
            ],
            ...$common,
        ];
    }

    /**
     * The products of the whole catalog or of categories, by title.
     *
     * @param list<string>|null $categoryIds
     *
     * @return list<Product>
     */
    private function list(?array $categoryIds): array
    {
        if (null === $categoryIds) {
            return $this->products->fetch(['order' => ['title', 'id']])->all();
        }

        $ids = [];

        foreach ($this->links->fetch(['where' => ['targetType' => Products::TYPE, 'categoryId' => $categoryIds]]) as $link) {
            $ids[$link->targetId] = $link->targetId;
        }

        $ids = array_values($ids);

        return [] === $ids ? [] : $this->products->fetch(['where' => ['id' => $ids], 'order' => ['title', 'id']])->all();
    }

    /**
     * @return array{title: string, href: string}|null
     */
    private function categoryLink(BlockContext $context, Category $category, string $language): ?array
    {
        $href = $this->pages->href($context, $category->id);

        return null === $href ? null : ['title' => $category->titleIn([$language]), 'href' => $href];
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Product $product, ?Price $price, BlockContext $context): array
    {
        return [
            'id' => $product->id,
            'title' => $product->title,
            'sku' => $product->sku,
            'href' => $this->urls->href(Target::parse(Products::TYPE . ':' . $product->id), $context->language),
            'image' => $this->rendering->image($product->media()[0] ?? null, $product->title, ShopRendering::CARD_SIZES, '1:1'),
            'short' => $product->shortDescription,
            'stock' => ShopRendering::stock($product),
            'orderable' => $product->orderable,
            'minQuantity' => $product->minQuantity,
            'quantityStep' => $product->quantityStep,
            'price' => null === $price ? null : $this->rendering->price($price, $context->language),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Product $product, BlockContext $context): array
    {
        $price = $this->rendering->prices([$product])[$product->providerId] ?? null;
        $media = $product->media();
        $categories = [];

        foreach ($this->links->fetch(['where' => ['targetType' => Products::TYPE, 'targetId' => $product->id]]) as $link) {
            $category = $this->categories->fetchById($link->categoryId);
            $item = null === $category ? null : $this->categoryLink($context, $category, $context->contentLanguage);

            if (null !== $item) {
                $categories[] = $item;
            }
        }

        return [
            ...$this->card($product, $price, $context),
            'gtin' => $product->gtin,
            'image' => $this->rendering->image($media[0] ?? null, $product->title, ShopRendering::DETAIL_SIZES, null, true),
            'gallery' => array_map(fn(string $id) => $this->rendering->image($id, $product->title, '8rem', '1:1'), \array_slice($media, 1)),
            'description' => null === $product->description ? '' : RichText::standard()->toHtml($product->description),
            'deliveryTime' => $product->deliveryTime,
            'categories' => $categories,
        ];
    }

    private function here(BlockContext $context, string $base): string
    {
        return null === $context->subpage ? $base : $this->urls->href($context->subpage->target, $context->language) ?? $base;
    }
}
