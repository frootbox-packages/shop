<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Catalog;

use DateTimeImmutable;
use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Category\Categories;
use Frootbox\Core\Category\CategoryEditor;
use Frootbox\Core\Category\ObjectCategories;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Media\Media;
use Frootbox\Core\Media\MediaEditor;
use Frootbox\Core\Media\MediaFolders;
use Frootbox\Core\Media\Uploads;
use Frootbox\Core\Page\Slug;
use Frootbox\Core\ProjectPaths;
use Frootbox\Core\Tag\ObjectTags;
use Frootbox\Core\Tag\TagEditor;
use Frootbox\Core\Tag\TagLinks;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\RichText\HtmlReader;
use Frootbox\Shop\Provider\CatalogChange;
use Frootbox\Shop\Provider\ChangeKind;
use Frootbox\Shop\Provider\ShopCategory;
use Frootbox\Shop\Provider\ShopImage;
use Frootbox\Shop\Provider\ShopProduct;
use Frootbox\Shop\Provider\ShopProvider;
use Frootbox\Shop\Provider\StalePosition;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Brings the mirror of the catalog up to date with the provider
 * (decision 0311): products, categories of the core in the group of the
 * catalog, and copies of the images in the media library, folder "Shop".
 * Runs inside frootbox/shop/catalog.sync.
 */
final readonly class CatalogSync
{
    public const string ACTION = 'frootbox/shop/catalog.sync';

    public const string FOLDER = 'Shop';

    public function __construct(
        private Products $products,
        private Links $links,
        private SyncStates $states,
        private Categories $categories,
        private CategoryEditor $categoryEditor,
        private ObjectCategories $objectCategories,
        private ObjectTags $objectTags,
        private TagLinks $tagLinks,
        private MediaEditor $media,
        private MediaFolders $folders,
        private Media $stored,
        private Uploads $uploads,
        private Languages $languages,
        private ProjectPaths $paths,
        private LoggerInterface $logger,
    ) {}

    public function run(ShopProvider $provider, bool $full = false): SyncResult
    {
        // A position counts only in the catalog it came from; a copy of
        // the website connected to another installation starts over.
        $source = self::source($provider);
        $state = $this->states->catalog();
        $since = $full || $state->source !== $source ? null : $state->position;

        try {
            [$changes, $until] = $this->changes($provider, $since);
        } catch (StalePosition) {
            [$since, [$changes, $until]] = [null, $this->changes($provider, null)];
        }

        $full = null === $since;
        $categories = 0;

        if ($full || \in_array(ChangeKind::Category, array_column($changes, 'kind'), true)) {
            $categories = $this->categories($provider);
        }

        $updated = 0;
        $deleted = 0;
        $images = 0;
        $seen = [];

        foreach ($changes as $change) {
            if (ChangeKind::Product !== $change->kind) {
                continue;
            }

            $product = $change->deleted ? null : $provider->product($change->id);

            if (null === $product) {
                $deleted += $this->delete($change->id) ? 1 : 0;

                continue;
            }

            $seen[$product->id] = true;
            [$changed, $copied] = $this->product($provider, $product);
            $updated += $changed ? 1 : 0;
            $images += $copied;
        }

        // Without a position every product of the channel came; the others are gone.
        if ($full) {
            foreach ($this->products->fetch() as $product) {
                if (!isset($seen[$product->providerId])) {
                    $deleted += $this->delete($product->providerId) ? 1 : 0;
                }
            }
        }

        $removed = $updated + $deleted > 0 ? $this->prune() : 0;
        $this->states->save($until, new DateTimeImmutable(), null, $source);

        return new SyncResult($updated, $deleted, $categories, $images, $full, $removed);
    }

    /**
     * The catalog of the provider as a short hash, with the provider
     * itself: another provider has other positions too.
     */
    private static function source(ShopProvider $provider): string
    {
        return hash('sha256', $provider::class . "\n" . $provider->source());
    }

    /**
     * All changes since a position and the position for the next time.
     *
     * @return array{list<CatalogChange>, string|null}
     */
    private function changes(ShopProvider $provider, ?string $since): array
    {
        $changes = [];
        $cursor = null;
        $until = $since;

        do {
            $page = $provider->changes($since, $cursor);
            array_push($changes, ...$page->changes);
            $cursor = $page->next;
            $until = $page->until ?? $until;
        } while (null !== $cursor);

        return [$changes, $until];
    }

    /**
     * Creates, changes, moves and deletes the categories of the catalog
     * like those of the provider; returns how many changed.
     */
    private function categories(ShopProvider $provider): int
    {
        $links = $this->links->all(Link::CATEGORY);
        $known = [];
        $changed = 0;
        $position = [];

        foreach ($provider->categories() as $category) {
            $parentId = null === $category->parentId ? null : $known[$category->parentId] ?? null;
            $place = $position[$parentId ?? ''] = ($position[$parentId ?? ''] ?? -1) + 1;
            $link = $links[$category->id] ?? null;
            $local = null === $link ? null : $this->categories->fetchById($link->localId);
            $translations = $this->categoryTexts($category);

            if (null === $local) {
                $local = $this->categoryEditor->create(self::ACTION, Products::CATEGORIES, $translations, $parentId, $place);
                $this->links->put(Link::CATEGORY, $category->id, $local->id);
                ++$changed;
            } else {
                if (!self::equal($local->translations, $translations)) {
                    $this->categoryEditor->update(self::ACTION, $local->id, $translations);
                    ++$changed;
                }

                if ($local->parentId !== $parentId || $local->position !== $place) {
                    $this->categoryEditor->move(self::ACTION, $local->id, $parentId, $place);
                    ++$changed;
                }
            }

            $known[$category->id] = $local->id;
        }

        foreach ($links as $externalId => $link) {
            if (!isset($known[$externalId])) {
                if (null !== $this->categories->fetchById($link->localId)) {
                    $this->categoryEditor->delete(self::ACTION, $link->localId);
                }

                $this->links->delete($link->id);
                ++$changed;
            }
        }

        return $changed;
    }

    /**
     * The texts of a category in every language; the provider has one.
     *
     * @return array<string, array<string, string>>
     */
    private function categoryTexts(ShopCategory $category): array
    {
        $texts = array_filter(['title' => mb_substr($category->title, 0, CategoryEditor::TITLE_LENGTH), 'slug' => self::slug($category->slug, $category->title), 'description' => mb_substr(trim($category->description ?? ''), 0, CategoryEditor::DESCRIPTION_LENGTH)], static fn(string $value): bool => '' !== $value);

        return array_fill_keys($this->languages->codes(), $texts);
    }

    /**
     * Writes a product; returns whether it changed and how many images
     * were copied.
     *
     * @return array{bool, int}
     */
    private function product(ShopProvider $provider, ShopProduct $product): array
    {
        $copied = 0;
        $media = [];

        foreach ($product->images as $image) {
            [$id, $new] = $this->image($provider, $image, $product->title);

            if (null !== $id) {
                $media[] = $id;
                $copied += $new ? 1 : 0;
            }
        }

        $description = trim($product->description ?? '');
        $values = [
            'providerId' => $product->id,
            'sku' => mb_substr($product->sku, 0, 100),
            'gtin' => null === $product->gtin || '' === $product->gtin ? null : mb_substr($product->gtin, 0, 50),
            'slug' => $this->freeSlug(self::slug($product->slug, $product->title), $product->id),
            'title' => mb_substr($product->title, 0, 300),
            'shortDescription' => null === $product->shortDescription || '' === trim($product->shortDescription) ? null : mb_substr(trim($product->shortDescription), 0, 2000),
            'description' => '' === $description ? null : new HtmlReader(static fn(): null => null)->read($description),
            'images' => $media,
            'stock' => $product->stock->value,
            'orderable' => $product->orderable,
            'deliveryTime' => null === $product->deliveryTime || '' === $product->deliveryTime ? null : mb_substr($product->deliveryTime, 0, 200),
            'unit' => mb_substr($product->unit, 0, 20),
            'minQuantity' => max(1, $product->minQuantity),
            'quantityStep' => max(1, $product->quantityStep),
        ];

        $stored = $this->products->byProviderId($product->id);
        $same = null !== $stored && self::same($stored, $values);
        $after = match (true) {
            null === $stored => $this->products->insert($values),
            $same => $stored,
            default => $this->products->update($stored->id, $values),
        };
        $categoryIds = [];
        $links = $this->links->all(Link::CATEGORY);

        foreach ($product->categories as $externalId) {
            if (isset($links[$externalId])) {
                $categoryIds[] = $links[$externalId]->localId;
            }
        }

        $this->objectCategories->sync(Products::TYPE, $after->id, $categoryIds);
        $tagged = $this->tags($product, $after->id);
        // The copies of its images are public with the product (decision 0306).
        $this->media->makePublic($media);

        return [!$same || $tagged || $copied > 0, $copied];
    }

    /**
     * Gives the product the tags of the provider as tags of the core,
     * found by key or label or created (decision 0320); returns whether
     * they changed. Tags that the core does not take are left out.
     */
    private function tags(ShopProduct $product, string $id): bool
    {
        $labels = array_values(array_filter(
            array_map(static fn(string $tag): string => trim($tag), $product->tags),
            static fn(string $tag): bool => '' !== $tag && mb_strlen($tag) <= TagEditor::LABEL_LENGTH,
        ));

        try {
            $wanted = $this->objectTags->check(self::ACTION, Products::TYPE, null, $labels);
        } catch (InvalidInputException $error) {
            $this->logger->warning('The tags of the product {id} of the shop are not all allowed: {message}', ['id' => $product->id, 'message' => $error->getMessage()]);
            $wanted = [];
        }

        $before = $this->tagLinks->idsOf(Products::TYPE, [$id])[$id] ?? [];
        sort($before);
        $sorted = $wanted;
        sort($sorted);

        if ($before === $sorted) {
            return false;
        }

        $this->objectTags->sync(Products::TYPE, $id, $wanted);

        return true;
    }

    /**
     * Whether a stored product has these values already.
     *
     * @param array<string, mixed> $values
     */
    private static function same(Product $product, array $values): bool
    {
        $stored = get_object_vars($product);

        foreach ($values as $key => $value) {
            if (!\array_key_exists($key, $stored) || !self::equal($stored[$key], $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The medium for an image, copied anew when its version changed;
     * whether it was copied now.
     *
     * @return array{string|null, bool}
     */
    private function image(ShopProvider $provider, ShopImage $image, string $title): array
    {
        $link = $this->links->find(Link::IMAGE, $image->id);

        if (null !== $link && $link->version === $image->version) {
            return [$link->localId, false];
        }

        $file = $this->paths->var('shop/' . bin2hex(random_bytes(8)));

        try {
            if (!is_dir(\dirname($file))) {
                @mkdir(\dirname($file), 0o775, true);
            }

            $provider->downloadImage($image, $file);
            $name = Slug::latin($title) . self::extension($image->url);
            $upload = $this->uploads->store($file, '' === $name ? 'image.jpg' : $name);
            $alt = trim($image->alt ?? '');
            $texts = array_fill_keys($this->languages->codes(), ['alt' => '' === $alt ? $title : $alt]);
            $existing = null === $link ? null : $this->stored->fetchById($link->localId);

            if (null === $existing) {
                $medium = $this->media->create(self::ACTION, $upload, $this->folder(), $texts, []);
            } elseif ($this->uploads->hash($upload) === $existing->hash) {
                // A new version with the same file: nothing to copy.
                $this->uploads->remove($upload);
                $this->links->put(Link::IMAGE, $image->id, $existing->id, $image->version);

                return [$existing->id, false];
            } else {
                // A public medium takes a new file as a draft, published at once.
                $this->media->replace(self::ACTION, $existing->id, $upload);
                $medium = $this->media->publishDrafts(self::ACTION, [$existing->id])[0];
            }
        } catch (Throwable $error) {
            // One image that does not come keeps the old copy, if any.
            $this->logger->warning('The image {id} of the shop could not be copied: {message}', ['id' => $image->id, 'message' => $error->getMessage(), 'exception' => $error]);

            return [$link?->localId, false];
        } finally {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->links->put(Link::IMAGE, $image->id, $medium->id, $image->version);

        return [$medium->id, true];
    }

    /**
     * Removes the copies of images that no product uses any more (decision
     * 0306). One an editor put on a published page stays in the media
     * library, but no longer belongs to the shop.
     */
    private function prune(): int
    {
        $used = [];

        foreach ($this->products->fetch() as $product) {
            foreach ($product->media() as $id) {
                $used[$id] = true;
            }
        }

        $removed = 0;

        foreach ($this->links->all(Link::IMAGE) as $link) {
            if (isset($used[$link->localId])) {
                continue;
            }

            try {
                $this->media->delete($link->localId);
                ++$removed;
            } catch (EntityNotFoundException) {
                // Deleted already, e.g. by hand.
            } catch (ConflictException) {
                $this->logger->info('The image {id} of the shop stays, a published page uses it.', ['id' => $link->localId]);
            }

            $this->links->delete($link->id);
        }

        return $removed;
    }

    private function delete(string $providerId): bool
    {
        $product = $this->products->byProviderId($providerId);

        if (null === $product) {
            return false;
        }

        $this->objectCategories->clear(Products::TYPE, $product->id);
        $this->objectTags->clear(Products::TYPE, $product->id);
        $this->products->delete($product->id);

        return true;
    }

    /**
     * The folder "Shop" at the top of the media library.
     */
    private function folder(): string
    {
        $folder = $this->folders->fetchOne(['where' => ['parentId' => null, 'name' => self::FOLDER]]);

        return ($folder ?? $this->media->createFolder(self::ACTION, self::FOLDER, null))->id;
    }

    /**
     * A slug no other product has: with "-2", "-3" … otherwise.
     */
    private function freeSlug(string $slug, string $providerId): string
    {
        $candidate = $slug;

        for ($number = 2; ; ++$number) {
            $other = $this->products->bySlug($candidate);

            if (null === $other || $other->providerId === $providerId) {
                return $candidate;
            }

            $candidate = $slug . '-' . $number;
        }
    }

    /**
     * Whether two values are the same, arrays regardless of the order of their keys.
     */
    private static function equal(mixed $one, mixed $other): bool
    {
        return \is_array($one) && \is_array($other) ? self::sorted($one) === self::sorted($other) : $one === $other;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    private static function sorted(array $values): array
    {
        if (!array_is_list($values)) {
            ksort($values);
        }

        return array_map(static fn(mixed $value): mixed => \is_array($value) ? self::sorted($value) : $value, $values);
    }

    private static function slug(string $slug, string $title): string
    {
        $slug = Slug::isValid($slug) ? $slug : Slug::fromTitle('' === $slug ? $title : $slug);

        return '' === $slug ? 'p' : mb_substr($slug, 0, 200);
    }

    private static function extension(string $url): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, \PHP_URL_PATH), \PATHINFO_EXTENSION));

        return \in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? '.' . $extension : '.jpg';
    }
}
