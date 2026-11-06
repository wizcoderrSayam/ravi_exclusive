<?php

namespace Tests\Feature;

use Database\Seeders\RaviProductListSeeder;
use Database\Seeders\StorefrontCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Peer\CMS\Models\Category;
use Peer\Ecommerce\Models\CategoryMedia;
use Peer\Ecommerce\Models\Product;
use Tests\TestCase;

class StorefrontCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('cms.media.disk'));
    }

    public function test_it_seeds_the_backup_category_tree_and_images_without_products(): void
    {
        $this->seed(StorefrontCategorySeeder::class);

        $topLevel = Category::query()->whereNull('parent_id')->orderBy('id')->get();

        $this->assertSame(['girls', 'boys', 'men', 'women'], $topLevel->pluck('slug')->all());
        $this->assertSame(14, Category::query()->whereNotNull('parent_id')->count());
        $this->assertSame(
            ['Lehenga', 'Sharara / Palazzo', 'Patiala Salwar Suits / Farshi Salwar', 'Frocks & Gowns'],
            $topLevel->firstWhere('slug', 'girls')->children()->orderBy('id')->pluck('name')->all(),
        );
        $this->assertSame(
            ['Kurta Pajamas', 'Waistcoat Sets', 'Indo-Westerns'],
            $topLevel->firstWhere('slug', 'men')->children()->orderBy('id')->pluck('name')->all(),
        );

        $this->assertSame('Categories/boys.jpg', CategoryMedia::where('category_id', $topLevel->firstWhere('slug', 'boys')->id)->sole()->path);

        foreach (Category::all() as $category) {
            $image = CategoryMedia::where('category_id', $category->id)->sole();
            Storage::disk(config('cms.media.disk'))->assertExists($image->path);
            $this->assertStringStartsWith('image/', $image->mime_type, "{$category->name} has no image");
        }

        $this->assertSame(18, CategoryMedia::count());
        $this->assertSame(0, Product::count());
    }

    public function test_it_can_run_again_without_duplicating_categories_or_images(): void
    {
        $this->seed(StorefrontCategorySeeder::class);
        $this->seed(StorefrontCategorySeeder::class);

        $this->assertSame(18, Category::count());
        $this->assertSame(18, CategoryMedia::count());
    }

    public function test_a_photo_chosen_in_the_admin_is_kept(): void
    {
        $this->seed(StorefrontCategorySeeder::class);
        $boys = Category::firstWhere('slug', 'boys');
        CategoryMedia::where('category_id', $boys->id)->update(['path' => 'Categories/chosen-in-admin.jpg']);

        $this->seed(StorefrontCategorySeeder::class);

        $this->assertSame('Categories/chosen-in-admin.jpg', CategoryMedia::where('category_id', $boys->id)->sole()->path);
    }

    public function test_ravi_products_use_the_backup_categories(): void
    {
        $this->seed([StorefrontCategorySeeder::class, RaviProductListSeeder::class]);

        $category = Product::firstWhere('slug', 'ivory-royal-heritage-sharara-set')->categories->sole();

        $this->assertSame(18, Category::count());
        $this->assertSame(49, Product::count());
        $this->assertSame('sharara-palazzo', $category->slug);
        $this->assertSame('girls', $category->parent->slug);
    }
}
