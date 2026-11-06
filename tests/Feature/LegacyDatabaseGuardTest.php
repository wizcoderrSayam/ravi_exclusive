<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LegacyDatabaseGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_before_every_migration_a_legacy_database_has_pending(): void
    {
        $this->assertSame('0001_01_01_000003', substr(basename($this->path()), 0, 17));

        $ecommerceAndCms = array_merge(
            glob(base_path('packages/ecommerce/database/migrations/*.php')),
            glob(base_path('vendor/carepeerinfotech/cms/database/migrations/*.php')),
        );

        foreach ($ecommerceAndCms as $migration) {
            $this->assertGreaterThan(basename($this->path()), basename($migration));
        }
    }

    public function test_it_stops_on_the_old_stores_database(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table): void {
            $table->id();
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ecommerce:import-legacy');

        $this->migration()->up();
    }

    public function test_it_lets_a_new_database_through(): void
    {
        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('homepage_sections'));
    }

    private function path(): string
    {
        return database_path('migrations/0001_01_01_000003_refuse_to_migrate_a_legacy_store_database.php');
    }

    private function migration(): Migration
    {
        return require $this->path();
    }
}
