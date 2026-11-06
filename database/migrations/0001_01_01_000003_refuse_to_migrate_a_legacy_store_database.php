<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Runs before every other pending migration, so a deploy pointed at the store's old
     * database stops here with nothing changed. The CMS's tables clash with the old store's
     * (categories, settings, contact_messages), so migrating it would stop halfway. The old
     * store moves by importing it into a new database instead.
     */
    public function up(): void
    {
        if (Schema::hasTable('homepage_sections')) {
            throw new RuntimeException(
                'This database belongs to the old store, so nothing was migrated. Set DB_DATABASE to a new, empty database, '
                .'run `php artisan migrate`, then copy the store across with `php packages/ecommerce/bin/export-legacy.php` '
                .'and `php artisan ecommerce:import-legacy`. To keep the old store running for now, deploy its last commit again.'
            );
        }
    }

    public function down(): void {}
};
