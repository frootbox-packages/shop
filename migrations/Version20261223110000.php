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
 * The catalog of the provider a position of the sync belongs to
 * (decision 0323).
 */
final class Version20261223110000 extends Migration
{
    public function getDescription(): string
    {
        return 'Adds frootbox_shop_sync.source';
    }

    public function up(Schema $schema): void
    {
        $this->alterTable('frootbox_shop_sync', function (Blueprint $table): void {
            $table->string('source', 64)->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $this->alterTable('frootbox_shop_sync', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
}
