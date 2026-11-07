<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Tests;

use Frootbox\Core\Category\Categories;
use Frootbox\Core\Category\CategoryLinks;
use Frootbox\Core\Media\Media;
use Frootbox\Core\Media\MediaFolders;
use Frootbox\Core\Queue\Jobs;
use Frootbox\Core\Tag\TagLinks;
use Frootbox\Core\Tag\Tags;
use Frootbox\Shop\Catalog\CatalogSync;
use Frootbox\Shop\Catalog\Link;
use Frootbox\Shop\Catalog\Links;
use Frootbox\Shop\Catalog\ProductPlugin;
use Frootbox\Shop\Catalog\Products;
use Frootbox\Shop\Catalog\SyncCatalog;
use Frootbox\Shop\Catalog\WebhookHandler;
use Frootbox\Shop\Provider\CatalogChange;
use Frootbox\Shop\Provider\ChangeKind;
use Frootbox\Shop\Provider\ShopCategory;
use Frootbox\Shop\Provider\ShopImage;
use Frootbox\Shop\Tests\Support\FakeShop;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CatalogSync::class)]
#[CoversClass(SyncCatalog::class)]
#[CoversClass(ProductPlugin::class)]
#[CoversClass(WebhookHandler::class)]
final class CatalogTest extends ShopTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeShop::$categories = [
            new ShopCategory('c1', null, 'kakteen', 'Kakteen', 'Stachelig und genügsam.'),
            new ShopCategory('c2', 'c1', 'echinocactus', 'Echinocactus'),
            new ShopCategory('c3', null, 'zubehoer', 'Zubehör'),
        ];
        FakeShop::$products = [
            '11' => FakeShop::item('11', 'Echinocactus grusonii', ['c2'], [new ShopImage('img1', 'https://shop.example/a.png', 'v1', 'Goldkugelkaktus')]),
            '12' => FakeShop::item('12', 'Kakteenerde', ['c3'], tags: ['startseite']),
        ];
    }

    public function testTheFirstSyncMirrorsTheWholeCatalog(): void
    {
        $result = $this->runner()->run(new SyncCatalog());

        self::assertTrue($result->full);
        self::assertSame(2, $result->products);
        self::assertSame(1, $result->images);

        $product = $this->get(Products::class)->byProviderId('11');
        self::assertNotNull($product);
        self::assertSame('echinocactus-grusonii', $product->slug);
        self::assertSame(6, $product->quantityStep);
        // The description is rich text: no script.
        self::assertStringNotContainsString('script', (string) json_encode($product->description));

        // The image is a public copy in the folder "Shop" with its alt text.
        $medium = $this->get(Media::class)->fetchById($product->media()[0]);
        self::assertNotNull($medium);
        self::assertTrue($medium->public);
        self::assertSame('Shop', $this->get(MediaFolders::class)->fetchById((string) $medium->folderId)?->name);

        // Categories of the core, as a tree.
        $tree = $this->get(Categories::class)->tree(Products::CATEGORIES);
        self::assertSame(['Kakteen', 'Echinocactus', 'Zubehör'], array_map(static fn($item): ?string => $item->title('de'), $tree));
        self::assertCount(1, $this->get(CategoryLinks::class)->fetch(['where' => ['targetType' => Products::TYPE, 'targetId' => $product->id]])->all());

        // Nothing changed: nothing written, the image is not copied again.
        FakeShop::$downloads = [];
        $again = $this->runner()->run(new SyncCatalog());
        self::assertFalse($again->full);
        self::assertFalse($again->changed());
        self::assertSame([], FakeShop::$downloads);
    }

    public function testLaterSyncsTakeTheChanges(): void
    {
        $this->runner()->run(new SyncCatalog());

        FakeShop::$products['11'] = FakeShop::item('11', 'Goldkugelkaktus', ['c1'], [new ShopImage('img1', 'https://shop.example/a.png', 'v2')]);
        unset(FakeShop::$products['12']);
        FakeShop::$categories = [FakeShop::$categories[0]];
        FakeShop::$changes = [new CatalogChange(ChangeKind::Product, '11'), new CatalogChange(ChangeKind::Product, '12', true), new CatalogChange(ChangeKind::Category, 'c2', true)];

        $result = $this->runner()->run(new SyncCatalog());

        self::assertSame(1, $result->products);
        self::assertSame(1, $result->deleted);
        self::assertSame(1, $result->images);
        self::assertSame(['img1@v1', 'img1@v2'], FakeShop::$downloads);
        self::assertNull($this->get(Products::class)->byProviderId('12'));
        self::assertSame('Goldkugelkaktus', $this->get(Products::class)->byProviderId('11')?->title);
        self::assertSame(['Kakteen'], array_map(static fn($item): ?string => $item->title('de'), $this->get(Categories::class)->tree(Products::CATEGORIES)));
    }

    public function testRemovesImagesThatNoProductUsesAnyMore(): void
    {
        $this->runner()->run(new SyncCatalog());
        $medium = $this->get(Products::class)->byProviderId('11')?->media()[0] ?? null;
        self::assertNotNull($medium);

        FakeShop::$products['11'] = FakeShop::item('11', 'Echinocactus grusonii', ['c2'], []);
        FakeShop::$changes = [new CatalogChange(ChangeKind::Product, '11')];

        $result = $this->runner()->run(new SyncCatalog());

        self::assertSame(1, $result->removedImages);
        self::assertNull($this->get(Media::class)->fetchById($medium));
        self::assertSame([], $this->get(Links::class)->all(Link::IMAGE));
    }

    public function testATooOldPositionStartsAnew(): void
    {
        $this->runner()->run(new SyncCatalog());
        FakeShop::$stale = true;
        unset(FakeShop::$products['12']);

        $result = $this->runner()->run(new SyncCatalog());

        self::assertTrue($result->full);
        self::assertSame(1, $result->deleted);
    }

    public function testAnotherCatalogStartsAnew(): void
    {
        // A copy of the website made with one installation of the shop
        // and connected to another: the position means nothing there.
        $this->runner()->run(new SyncCatalog());
        FakeShop::$source = 'https://live.example client';
        FakeShop::$categories = [FakeShop::$categories[0], FakeShop::$categories[1]];

        $result = $this->runner()->run(new SyncCatalog());

        self::assertTrue($result->full);
        self::assertSame(['Kakteen', 'Echinocactus'], array_map(static fn($item): ?string => $item->title('de'), $this->get(Categories::class)->tree(Products::CATEGORIES)));
        self::assertFalse($this->runner()->run(new SyncCatalog())->full);
    }

    public function testCategoriesAndProductsHaveAddressesBelowTheCatalog(): void
    {
        $start = $this->page(null, 'Start');
        $this->catalogPage($start);
        $this->runner()->run(new SyncCatalog());

        $catalog = $this->html('/shop');
        self::assertStringContainsString('Echinocactus grusonii', $catalog);
        self::assertStringContainsString('href="/shop/kakteen"', $catalog);
        // Kakteenerde has no image: its card shows the placeholder.
        self::assertSame(1, substr_count($catalog, 'class="shop-placeholder"'));

        $category = $this->html('/shop/kakteen/echinocactus');
        self::assertStringContainsString('Echinocactus grusonii', $category);
        self::assertStringNotContainsString('Kakteenerde', $category);
        self::assertStringNotContainsString('class="shop-placeholder"', $category);

        $product = $this->html('/shop/echinocactus-grusonii');
        self::assertStringContainsString('<h1', $product);
        self::assertStringContainsString('<strong>schöner</strong>', $product);
        self::assertStringContainsString('name="quantity" value="6" min="6" step="6"', $product);
        self::assertStringNotContainsString('11,90', $product);
    }

    public function testTagsOfTheProviderBecomeTagsOfTheCore(): void
    {
        $this->runner()->run(new SyncCatalog());
        $soil = $this->get(Products::class)->byProviderId('12');
        self::assertNotNull($soil);
        $tag = $this->get(Tags::class)->byKey('startseite');
        self::assertNotNull($tag);
        self::assertSame([$soil->id => [$tag->id]], $this->get(TagLinks::class)->idsOf(Products::TYPE, [$soil->id]));

        FakeShop::$products['12'] = FakeShop::item('12', 'Kakteenerde', ['c3']);
        FakeShop::$changes = [new CatalogChange(ChangeKind::Product, '12')];
        self::assertSame(1, $this->runner()->run(new SyncCatalog())->products);
        self::assertSame([], $this->get(TagLinks::class)->idsOf(Products::TYPE, [$soil->id]));
    }

    public function testTheStartPageShowsOnlyTaggedProductsWithItsTexts(): void
    {
        $start = $this->page(null, 'Start');
        $this->catalogPage($start, 'Shop', ['options' => ['tag' => 'startseite'], 'fields' => ['title' => 'Willkommen', 'productsTitle' => 'Saisonale Angebote']]);
        $this->runner()->run(new SyncCatalog());

        $catalog = $this->html('/shop');
        self::assertStringContainsString('Willkommen', $catalog);
        self::assertStringContainsString('Saisonale Angebote', $catalog);
        self::assertStringContainsString('Kakteenerde', $catalog);
        self::assertStringNotContainsString('Echinocactus grusonii', $catalog);

        // A category shows all its products, the heading of the offers is gone.
        $category = $this->html('/shop/kakteen');
        self::assertStringContainsString('Echinocactus grusonii', $category);
        self::assertStringNotContainsString('Saisonale Angebote', $category);
    }

    public function testTheSearchFindsInTheWholeCatalog(): void
    {
        $start = $this->page(null, 'Start');
        $this->catalogPage($start);
        $this->runner()->run(new SyncCatalog());

        $found = $this->html('/shop?q=GRUSONII');
        self::assertStringContainsString('Echinocactus grusonii', $found);
        self::assertStringNotContainsString('href="/shop/kakteenerde"', $found);
        self::assertStringContainsString('value="GRUSONII"', $found);

        self::assertStringContainsString('Kakteenerde', $this->html('/shop/kakteen?q=K-12'));
        self::assertStringNotContainsString('class="shop-card"', $this->html('/shop?q=100%25'));
    }

    public function testAWebhookOnlyQueuesASync(): void
    {
        $handler = $this->get(WebhookHandler::class);

        self::assertSame(401, $handler->handle(new ServerRequest('POST', 'https://example.org/_shop/webhook', ['webhook-signature' => 'wrong']))->getStatusCode());
        self::assertSame(0, $this->get(Jobs::class)->countDue(new \DateTimeImmutable('+1 minute')));

        self::assertSame(202, $handler->handle(new ServerRequest('POST', 'https://example.org/_shop/webhook', ['webhook-signature' => 'secret']))->getStatusCode());
        self::assertSame(1, $this->get(Jobs::class)->countDue(new \DateTimeImmutable('+1 minute')));
    }
}
