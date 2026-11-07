<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Tests;

use Frootbox\Core\Action\Channel;
use Frootbox\Core\Action\Context;
use Frootbox\Core\Action\CurrentContext;
use Frootbox\Core\Application;
use Frootbox\Core\Config\Environment;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\Extension\Registry;
use Frootbox\Core\Http\MiddlewareDispatcher;
use Frootbox\Core\Page\CreatePage;
use Frootbox\Core\Page\PageHandler;
use Frootbox\Core\Page\PublishPages;
use Frootbox\Core\Plugin\AssignPlugin;
use Frootbox\Core\Plugin\Space;
use Frootbox\Core\Tests\Support\FixtureSite;
use Frootbox\Core\Tests\Support\NotesTestCase;
use Frootbox\Core\Visitor\VisitorSession;
use Frootbox\Shop\Catalog\Links;
use Frootbox\Shop\Catalog\Products;
use Frootbox\Shop\Catalog\SyncStates;
use Frootbox\Shop\Provider\ShopProviderEntry;
use Frootbox\Shop\Tests\Support\FakeShop;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;

/**
 * A website of the core fixtures with the shop and a shop in memory.
 */
abstract class ShopTestCase extends NotesTestCase
{
    public const string ROOT = __DIR__ . '/..';

    /** The cookie of the visitor, kept from response to request. */
    protected ?string $cookie = null;

    protected function setUp(): void
    {
        FakeShop::reset();
        parent::setUp();
        $this->cookie = null;
    }

    protected function tables(): array
    {
        return [Products::TABLE, Links::TABLE, SyncStates::TABLE];
    }

    protected function environment(): Environment
    {
        return Environment::Production;
    }

    protected function extensions(): Extensions
    {
        $shop = ExtensionPackage::fromManifest('frootbox/shop', (string) realpath(self::ROOT), psr4: ['Frootbox\Shop\\' => ['src/'], 'Frootbox\Shop\Migrations\\' => ['migrations/']]);

        return new Extensions([...FixtureSite::extensions('base', 'site')->packages, $shop], 'shop');
    }

    protected function register(Registry $registry): void
    {
        $registry->add(ShopProviderEntry::class, new ShopProviderEntry(FakeShop::class, 'Fake'));
    }

    protected function config(): array
    {
        return ['app' => ['name' => 'Testsite', 'url' => 'https://example.org'], 'theme' => 'acme/site', 'languages' => ['de' => ['title' => 'Deutsch']]];
    }

    /**
     * A published page with blocks in its main area.
     *
     * @param list<array<string, mixed>> $blocks
     */
    protected function page(?string $parentId, string $title, array $blocks = []): string
    {
        $id = $this->runner()->run(new CreatePage('acme/base/Page', ['de' => ['title' => $title, 'content' => ['fields' => ['heading' => $title], 'areas' => ['main' => $blocks]]]], $parentId))->id;
        $this->runner()->run(new PublishPages([$id]));

        return $id;
    }

    /**
     * The page of the catalog, published.
     */
    protected function catalogPage(?string $parentId, string $title = 'Shop'): string
    {
        $id = $this->runner()->run(new CreatePage('acme/base/Page', ['de' => ['title' => $title, 'content' => ['fields' => ['heading' => $title]]]], $parentId))->id;
        $this->runner()->run(new AssignPlugin($id, Products::TYPE, Space::DEFAULT));
        $this->runner()->run(new PublishPages([$id]));

        return $id;
    }

    /**
     * A request of the browser of the visitor through all middleware,
     * with its cookie.
     *
     * @param array<string, mixed>|null $post
     * @param array<string, string>     $headers
     */
    protected function browse(string $path, ?array $post = null, array $headers = []): ResponseInterface
    {
        $headers += null === $post ? [] : ['Origin' => 'https://example.org'];
        $request = new ServerRequest(null === $post ? 'GET' : 'POST', 'https://example.org' . $path, $headers);
        parse_str((string) parse_url($path, \PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query)->withCookieParams(null === $this->cookie ? [] : [VisitorSession::COOKIE => $this->cookie]);

        if (null !== $post) {
            $request = $request->withParsedBody($post);
        }

        try {
            $response = new MiddlewareDispatcher(array_map($this->get(...), Application::MIDDLEWARE), $this->get(PageHandler::class))->handle($request);
        } finally {
            $this->get(CurrentContext::class)->set(Context::system(Channel::Console));
        }

        foreach ($response->getHeader('Set-Cookie') as $header) {
            if (1 === preg_match('/^fb_visitor=([0-9a-f]*);/', $header, $match)) {
                $this->cookie = '' === $match[1] ? null : $match[1];
            }
        }

        return $response;
    }

    protected function html(string $path): string
    {
        return (string) $this->browse($path)->getBody();
    }
}
