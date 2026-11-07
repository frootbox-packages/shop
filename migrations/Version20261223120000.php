<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Shop\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Frootbox\Database\Migration;
use Frootbox\Database\Schema\Blueprint;

/**
 * A sync of the catalog in short steps (decision 0324): where the running
 * one stands, and which run saw a product last.
 */
final class Version20261223120000 extends Migration
{
    public function getDescription(): string
    {
        return 'Adds frootbox_shop_sync.run and frootbox_shop_products.synced_in';
    }

    public function up(Schema $schema): void
    {
        $this->alterTable('frootbox_shop_sync', function (Blueprint $table): void {
            $table->json('run')->nullable();
        });
        $this->alterTable('frootbox_shop_products', function (Blueprint $table): void {
            $table->string('synced_in', 26)->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $this->alterTable('frootbox_shop_products', function (Blueprint $table): void {
            $table->dropColumn('synced_in');
        });
        $this->alterTable('frootbox_shop_sync', function (Blueprint $table): void {
            $table->dropColumn('run');
        });
    }
}
