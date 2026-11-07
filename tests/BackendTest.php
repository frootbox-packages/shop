<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Tests;

use Frootbox\Backend\Tests\Support\BackendTestCase;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\Extension\Registry;
use Frootbox\Core\ProjectPaths;
use Frootbox\Core\Tests\Support\FixtureSite;
use Frootbox\Shop\Backend\SyncHandler;
use Frootbox\Shop\Catalog\Links;
use Frootbox\Shop\Catalog\Products;
use Frootbox\Shop\Catalog\ShowSyncStatus;
use Frootbox\Shop\Catalog\SyncLock;
use Frootbox\Shop\Catalog\SyncStates;
use Frootbox\Shop\Provider\ShopCategory;
use Frootbox\Shop\Provider\ShopImage;
use Frootbox\Shop\Provider\ShopProviderEntry;
use Frootbox\Shop\Tests\Support\FakeShop;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SyncHandler::class)]
#[CoversClass(ShowSyncStatus::class)]
#[CoversClass(SyncLock::class)]
final class BackendTest extends BackendTestCase
{
    protected function setUp(): void
    {
        FakeShop::reset();
        parent::setUp();

        FakeShop::$categories = [new ShopCategory('c1', null, 'kakteen', 'Kakteen')];
        FakeShop::$products = [
            '11' => FakeShop::item('11', 'Echinocactus grusonii', ['c1'], [new ShopImage('img1', 'https://shop.example/a.png', 'v1')]),
            '12' => FakeShop::item('12', 'Kakteenerde', ['c1'], [new ShopImage('missing', 'https://shop.example/missing.png', 'v1')]),
        ];
    }

    protected function extensions(): Extensions
    {
        $shop = ExtensionPackage::fromManifest('frootbox/shop', (string) realpath(ShopTestCase::ROOT), psr4: ['Frootbox\Shop\\' => ['src/'], 'Frootbox\Shop\Migrations\\' => ['migrations/']]);

        return new Extensions([self::backend(), ...FixtureSite::extensions('base', 'site')->packages, $shop], 'shop-backend');
    }

    protected function register(Registry $registry): void
    {
        $registry->add(ShopProviderEntry::class, new ShopProviderEntry(FakeShop::class, 'Fake'));
    }

    protected function config(): array
    {
        return ['app' => ['name' => 'Testsite', 'url' => 'https://example.org'], 'theme' => 'acme/site', 'languages' => ['de' => ['title' => 'Deutsch']]];
    }

    protected function tables(): array
    {
        return [Products::TABLE, Links::TABLE, SyncStates::TABLE];
    }

    public function testAdminsSeeTheSyncAndRunItStepByStep(): void
    {
        $this->createUser('admin@example.org', ['admin']);
        $session = $this->signIn('admin@example.org');

        $html = $this->html($this->request('GET', '/cms/shop/sync', session: $session));
        self::assertStringContainsString('href="/cms/shop/sync" aria-current="page"', $html, 'The sidebar has the screen.');
        self::assertStringContainsString('Kopie des Katalogs von Fake', $html);
        self::assertStringContainsString('Noch nie abgeglichen', $html);
        self::assertStringContainsString('until="done"', $html);

        // The browser sends steps until done, the first one for the whole catalog.
        $first = $this->action('frootbox/shop/catalog.sync', ['full' => true, 'seconds' => 0], $session);
        self::assertFalse(\is_array($first['data'] ?? null) ? $first['data']['done'] : null);

        $html = $this->html($this->request('GET', '/cms/shop/sync', session: $session));
        self::assertStringContainsString('Der ganze Katalog wird abgeglichen.', $html);
        self::assertStringContainsString('0 von etwa 0 Produkten', $html);

        do {
            $step = $this->action('frootbox/shop/catalog.sync', ['full' => false, 'seconds' => 0], $session);
        } while (\is_array($step['data'] ?? null) && false === $step['data']['done']);

        $html = $this->html($this->request('GET', '/cms/shop/sync', session: $session));
        self::assertStringContainsString('Aktuell', $html);
        self::assertStringContainsString('Letzter Abgleich des ganzen Katalogs fertig:', $html);
        self::assertStringContainsString('1 Bild ließ sich nicht kopieren.', $html, 'An image the shop does not hand out is counted.');
        self::assertStringContainsString('Letzter Grund: The image is missing.', $html);
        self::assertStringContainsString('2 Produkte in der Kopie, davon 1 ohne Bild.', $html);
        // Decision 0334: where the time went and what writes the images.
        self::assertMatchesRegularExpression('/Rechenzeit: [0-9,]+ Minuten, etwa [0-9,]+ Sekunden je Produkt\.\s+Davon Shop [0-9]+ %, Bilder laden [0-9]+ %, Bilder verkleinern und speichern [0-9]+ %, Datenbank und Rest [0-9]+ %\./', $html);
        self::assertMatchesRegularExpression('/Bilder schreibt (GD|Imagick)/', $html);
    }

    public function testAStepWaitsForAnotherOne(): void
    {
        $this->createUser('admin@example.org', ['admin']);
        $session = $this->signIn('admin@example.org');
        $lock = $this->get(SyncLock::class);
        $lock->wait = 0;

        // Another process keeps the lock, e.g. a job of the queue.
        $file = $this->get(ProjectPaths::class)->var('shop/sync.lock');
        @mkdir(\dirname($file), 0o775, true);
        $other = fopen($file, 'c');
        self::assertNotFalse($other);
        flock($other, \LOCK_EX);

        try {
            // 503: the browser tries the same step again (decision 0331).
            $this->action('frootbox/shop/catalog.sync', [], $session, 503);
        } finally {
            flock($other, \LOCK_UN);
            fclose($other);
        }

        $this->action('frootbox/shop/catalog.sync', [], $session);
    }

    public function testEditorsSeeNoSync(): void
    {
        $this->createUser('editor@example.org', ['editor']);
        $session = $this->signIn('editor@example.org');

        self::assertStringNotContainsString('/cms/shop/sync', $this->html($this->request('GET', '/cms', session: $session)));
    }
}
