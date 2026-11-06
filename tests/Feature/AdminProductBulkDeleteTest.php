<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Peer\Ecommerce\Database\Seeders\EcommerceSeeder;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Models\Role;
use Tests\Concerns\BuildsStorefrontCatalog;
use Tests\TestCase;

class AdminProductBulkDeleteTest extends TestCase
{
    use BuildsStorefrontCatalog;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EcommerceSeeder::class);
        $this->seedCategoryTree();

        foreach (['One', 'Two', 'Three', 'Four'] as $index => $name) {
            $this->makeProduct("Kurta {$name}", $this->subcategory('Men', 'Kurta Pajamas'), 'RE-03'.$index, 1999);
        }

        $this->admin = User::factory()->create()->fresh();
        $this->admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));
    }

    public function test_the_product_list_has_a_delete_selected_button(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('id="bulk-delete" disabled data-url="'.route('admin.products.bulk-destroy').'"', false);
    }

    public function test_the_trash_tab_has_no_delete_selected_button(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.index', ['tab' => 'trash']))
            ->assertOk()
            ->assertDontSee('id="bulk-delete"', false);
    }

    public function test_the_selected_products_go_to_the_trash_and_can_come_back_with_their_variants(): void
    {
        $trashed = Product::query()->orderBy('id')->take(3)->get();
        $kept = Product::query()->orderBy('id')->skip(3)->first();

        $this->actingAs($this->admin)
            ->postJson(route('admin.products.bulk-destroy'), ['ids' => $trashed->modelKeys()])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => '3 products moved to trash.']);

        foreach ($trashed as $product) {
            $this->assertSoftDeleted($product);
            $this->assertDatabaseHas('product_variants', ['product_id' => $product->id]);
        }
        $this->assertNotSoftDeleted($kept);
    }

    public function test_nothing_is_deleted_without_a_selection(): void
    {
        $count = Product::count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.products.bulk-destroy'), ['ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        $this->assertSame($count, Product::count());
    }

    public function test_a_shopper_cannot_bulk_delete(): void
    {
        $product = Product::query()->first();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.products.bulk-destroy'), ['ids' => [$product->id]])
            ->assertForbidden();

        $this->assertNotSoftDeleted($product);
    }
}
