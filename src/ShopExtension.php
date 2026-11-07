<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop;

use Frootbox\Backend\Screen;
use Frootbox\Core\Category\CategoryGroup;
use Frootbox\Core\Extension\Extension;
use Frootbox\Core\Extension\Registry;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Core\View\TemplateFunction;
use Frootbox\Shop\Backend\SyncHandler;
use Frootbox\Shop\Cart\AddToCart;
use Frootbox\Shop\Cart\ShowCart;
use Frootbox\Shop\Cart\UpdateCart;
use Frootbox\Shop\Catalog\ProductPlugin;
use Frootbox\Shop\Catalog\Products;
use Frootbox\Shop\Catalog\ShowSyncStatus;
use Frootbox\Shop\Catalog\SyncCatalog;
use Frootbox\Shop\Catalog\SyncCommand;
use Frootbox\Shop\Catalog\SyncJob;
use Frootbox\Shop\Catalog\SyncOnSchedule;
use Frootbox\Shop\Catalog\WebhookHandler;
use Frootbox\Shop\Customer\ChangePassword;
use Frootbox\Shop\Customer\RequestPasswordReset;
use Frootbox\Shop\Customer\ResetPassword;
use Frootbox\Shop\Customer\ShowCustomer;
use Frootbox\Shop\Customer\SignIn;
use Frootbox\Shop\Customer\SignOut;
use Frootbox\Shop\Order\ListInvoices;
use Frootbox\Shop\Order\ListOrders;
use Frootbox\Shop\Order\PlaceOrder;
use Frootbox\Shop\Order\ShowInvoicePdf;
use Frootbox\Shop\Order\ShowOrder;
use Frootbox\Shop\Website\FormHandler;
use Frootbox\Shop\Website\InvoiceHandler;
use Frootbox\Shop\Website\ShopTemplates;

/**
 * The neutral shop (decision 0311): a provider such as avaro/shop
 * brings the shop, this module the mirror of the catalog, the blocks
 * and the actions for visitors.
 */
final class ShopExtension implements Extension
{
    public function register(Registry $registry): void
    {
        $registry->settings(ShopSettings::class);
        $registry->plugin(ProductPlugin::class);
        $registry->add(CategoryGroup::class, new CategoryGroup(Products::CATEGORIES, 'Categories of the shop', [Products::TYPE]));

        $registry->action(SyncCatalog::class);
        $registry->query(ShowSyncStatus::class);
        $registry->job(SyncJob::class);
        $registry->command(SyncCommand::class);
        $registry->listen(ScheduleRan::class, SyncOnSchedule::class);
        $registry->post(WebhookHandler::ROUTE, WebhookHandler::class, 'frootbox.shop.webhook');

        $registry->action(SignIn::class);
        $registry->action(SignOut::class);
        $registry->action(RequestPasswordReset::class);
        $registry->action(ResetPassword::class);
        $registry->action(ChangePassword::class);
        $registry->query(ShowCustomer::class);
        $registry->action(AddToCart::class);
        $registry->action(UpdateCart::class);
        $registry->query(ShowCart::class);
        $registry->action(PlaceOrder::class);
        $registry->query(ListOrders::class);
        $registry->query(ShowOrder::class);
        $registry->query(ListInvoices::class);
        $registry->query(ShowInvoicePdf::class);

        $registry->get(InvoiceHandler::ROUTE, InvoiceHandler::class, 'frootbox.shop.invoice');
        $registry->post(FormHandler::ROUTE, FormHandler::class, 'frootbox.shop.form');

        $registry->add(TemplateFunction::class, new TemplateFunction('shop_customer', ShopTemplates::class, 'customer'));
        $registry->add(TemplateFunction::class, new TemplateFunction('shop_cart', ShopTemplates::class, 'cart'));

        if (class_exists(Screen::class)) {
            $registry->mount('backend.path', '/cms')
                ->get('/shop/sync', SyncHandler::class, 'frootbox.shop.sync');
            $registry->add(Screen::class, new Screen('Shop sync', 'frootbox.shop.sync', Screen::WEBSITE, 'restore', SyncHandler::PERMISSION, order: 39));
        }
    }
}
