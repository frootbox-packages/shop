# frootbox/shop

A neutral shop for the Frootbox CMS. A provider such as `avaro/shop`
brings the shop itself: catalog, customers, prices, orders and invoices.
This module mirrors the catalog onto the website, so pages of products
and categories are fast and found by search engines, and gives visitors
a cart, sign-in, checkout and their account.

## Providers

A provider implements `Frootbox\Shop\Provider\ShopProvider` and
registers in its extension:

```php
$registry->add(ShopProviderEntry::class, new ShopProviderEntry(AcmeShop::class, 'Acme'));
```

It takes and gives the values of this module (`Channel`, `ShopProduct`,
`Quote`, `OrderSummary` …), never its own, and throws only a
`ShopException`: `ShopUnavailable`, `SignedOut`, `InputRejected` with a
message per field, `TotalChanged` with the new quote, `QuoteInvalid`,
`TooManyAttempts`, `NotFound`, `StalePosition`. Sums are cents as `int`,
unit prices decimal strings, never `float`. With one provider installed
it is active; with more, the setting `provider` names the class.

## The catalog

The action `frootbox/shop/catalog.sync` (technical, not undoable) asks
the provider for the changes since the last sync and writes products
(`frootbox_shop_products`), categories of the core in the group
`frootbox/shop/catalog` and copies of the images in the media library,
folder "Shop". The copies are at most 2000 pixels on the longer side;
the settings of the shop change the size (`imageSize`) or turn shrinking
off (`shrinkImages`), and a sync of the whole catalog brings the images
copied before to the new size. A position that is too old starts from the beginning.
It works in steps of a few seconds, each its own transaction, and says
with `done` whether the sync is through; the next call goes on where
the last one stopped, categories first. The scheduler queues a sync
every five minutes, a webhook of the provider at `POST /_shop/webhook`
at once, but never a second one while one waits; the job steps for up
to 10 seconds and queues the next one. Only one process syncs at a
time. The first one, or the whole catalog again, to the end:

```bash
php bin/frootbox shop:sync --full
```

With the backend installed, the screen "Shop sync" shows where the
sync stands, with images the provider did not hand out, and runs a sync
step by step in the browser (query `frootbox/shop/catalog.status`).

The tags of a product at the provider become tags of the core on the
product, found by key or label or created.

The catalog is kept in the shop: changes in the backend do not last.

## Pages

The catalog is a plugin: the page that shows it is the base of the
addresses. Categories lie as a path below it (`/shop/cacti/echinocactus`),
products flat with their slug (`/shop/echinocactus-grusonii`), because a
product can be in several categories. Prices need a signed-in customer;
guests see a link to sign in.

The view of the catalog searches with `?q=` in title, item number and
short text. With the option `tag` its start page shows only the
products with that tag, under the heading `productsTitle` and after the
text `intro`; categories still show all their products. For a sidebar
in a theme every view gets `navigation` (the categories at the top, the
path to the category shown open with the ones below it: `title`,
`href`, `current`, `open`, `children`), `search` (`action`, `q`) and
`start` (the address of the catalog).

Blocks: **Catalog** (`frootbox/shop/Catalog`, the view of the plugin),
**Cart**, **Checkout**, **Sign-in** (with forgotten and new password) and
**Account** (orders, invoices, addresses, password). In the head of
every page:

```twig
{% set customer = shop_customer() %}   {# name, email, number, company; null for a guest #}
<a href="{{ shop_cart().href }}">{{ shop_cart().pieces }}</a>
```

## Visitors

Signing in, the cart and ordering are public actions
(`frootbox/shop/customer.*`, `frootbox/shop/cart.*`,
`frootbox/shop/order.place`) on the session of the visitor. The forms
send them to `POST /_shop/<form>` and lead back to the page, so they
work without JavaScript; only from pages of the same website. The cart
keeps only products and pieces; prices and totals come fresh from the
provider every time. The token of the customer is encrypted in the
session. Without a browser (REST API, MCP) these actions answer 409.

## Source and issues

This package is developed in the [Frootbox monorepo](https://github.com/Frootbox/frootbox)
and mirrored here read-only. Questions, bugs and ideas go there, see the
[documentation](https://github.com/Frootbox/frootbox/tree/main/documentation).

## License

MIT. The icon is from [Lucide](https://lucide.dev), ISC license, see
`LICENSE-lucide`.
