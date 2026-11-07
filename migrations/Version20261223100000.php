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
 * The mirror of the catalog of the shop (decision 0311).
 */
final class Version20261223100000 extends Migration
{
    public function getDescription(): string
    {
        return 'Adds the mirror of the catalog of the shop';
    }

    public function up(Schema $schema): void
    {
        $this->createTable('frootbox_shop_products', function (Blueprint $table): void {
            $table->ulid();
            $table->string('provider_id', 100);
            $table->string('sku', 100);
            $table->string('gtin', 50)->nullable();
            $table->string('slug', 200);
            $table->string('title', 300);
            $table->string('short_description', 2000)->nullable();
            $table->json('description')->nullable();
            $table->json('images');
            $table->string('stock', 20);
            $table->boolean('orderable');
            $table->string('delivery_time', 200)->nullable();
            $table->string('unit', 20);
            $table->integer('min_quantity');
            $table->integer('quantity_step');
            $table->timestamps();
            $table->unique('provider_id');
            $table->unique('slug');
        });

        $this->createTable('frootbox_shop_links', function (Blueprint $table): void {
            $table->ulid();
            $table->string('kind', 20);
            $table->string('external_id', 100);
            $table->string('local_id', 26);
            $table->string('version', 100)->nullable();
            $table->timestamps();
            $table->unique(['kind', 'external_id']);
        });

        $this->createTable('frootbox_shop_sync', function (Blueprint $table): void {
            $table->string('id', 40);
            $table->primary('id');
            $table->text('position')->nullable();
            $table->dateTime('ran_at')->nullable();
            $table->string('error', 2000)->nullable();
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $this->dropTable('frootbox_shop_sync');
        $this->dropTable('frootbox_shop_links');
        $this->dropTable('frootbox_shop_products');
    }
}
