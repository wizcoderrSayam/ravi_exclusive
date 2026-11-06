<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Peer\Ecommerce\Database\Seeders\EcommerceSeeder;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_do_not_include_cms_pages(): void
    {
        $this->seed(EcommerceSeeder::class);

        // fresh() loads is_active's database default, which the admin middleware checks.
        $admin = User::factory()->create()->fresh();
        $admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Products');
        $response->assertSee('Revenue');
        $response->assertDontSee('CMS Pages');
    }

    public function test_admin_pages_render_the_seeded_store(): void
    {
        Storage::fake(config('cms.media.disk'));
        $this->seed(DatabaseSeeder::class);

        $admin = User::factory()->create()->fresh();
        $admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));
        $this->actingAs($admin);

        $product = Product::firstWhere('slug', 'ivory-royal-heritage-sharara-set');

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.orders.index'))->assertOk();
        $this->get(route('admin.products.index'))->assertOk()->assertSee($product->name);
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee($product->name)->assertSee(basename($product->images[0]), false);
    }
}
