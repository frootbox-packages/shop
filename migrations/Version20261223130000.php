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
 * What the last sync of the catalog did, for the screen of the sync
 * (decision 0326).
 */
final class Version20261223130000 extends Migration
{
    public function getDescription(): string
    {
        return 'Adds frootbox_shop_sync.last';
    }

    public function up(Schema $schema): void
    {
        $this->alterTable('frootbox_shop_sync', function (Blueprint $table): void {
            $table->json('last')->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $this->alterTable('frootbox_shop_sync', function (Blueprint $table): void {
            $table->dropColumn('last');
        });
    }
}
