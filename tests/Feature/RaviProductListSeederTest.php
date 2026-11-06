<?php

namespace Tests\Feature;

use Database\Seeders\RaviProductListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Peer\CMS\Models\Media;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Models\ProductVariant;
use Tests\TestCase;

class RaviProductListSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('cms.media.disk'));
    }

    public function test_it_seeds_every_product_from_the_export_with_its_photo(): void
    {
        $this->seed(RaviProductListSeeder::class);

        $products = Product::query()->where('brand', 'Ravi Exclusive')->whereIn('slug', $this->seededSlugs())->get();

        $this->assertSame(49, Product::count());
        $this->assertCount(49, $products);
        $this->assertSame(48, $products->filter(fn (Product $product): bool => $product->primaryImageUrl() !== null)->count());

        foreach ($products->filter(fn (Product $product): bool => $product->primaryImageUrl() !== null) as $product) {
            Storage::disk(config('cms.media.disk'))->assertExists($product->images[0]);
        }
    }

    public function test_a_product_is_sold_as_one_preselected_size_range_in_its_category(): void
    {
        $this->seed(RaviProductListSeeder::class);

        $product = Product::firstWhere('slug', 'ivory-royal-heritage-sharara-set');
        $variant = $product->variants()->sole();
        $category = $product->categories->sole();

        $this->assertSame('Sharara / Palazzo', $category->name);
        $this->assertSame('girls', $category->parent->slug);
        $this->assertSame('RE-00041', $product->sku);
        $this->assertSame('4177.00', (string) $product->price);
        $this->assertSame('24 to 38', $variant->option_label);
        $this->assertSame(['Size' => '24 to 38'], $variant->option_values);
        $this->assertTrue((bool) $variant->is_default);
        $this->assertSame(['Products/ivory-royal-heritage-sharara-set.webp'], $product->images);

        $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee('<button type="button" class="on" data-size="24 to 38">24 to 38</button>', false);
    }

    public function test_the_export_placeholder_size_is_replaced_by_the_real_range(): void
    {
        $this->seed(RaviProductListSeeder::class);

        $variant = Product::firstWhere('sku', 'RE-00015')->variants()->sole();

        $this->assertSame('24 to 38', $variant->option_label);
    }

    public function test_it_is_safe_to_re_run(): void
    {
        $this->seed(RaviProductListSeeder::class);
        $counts = [Product::count(), ProductVariant::count(), Media::count()];

        $this->seed(RaviProductListSeeder::class);

        $this->assertSame($counts, [Product::count(), ProductVariant::count(), Media::count()]);
    }

    public function test_a_photo_chosen_in_the_admin_is_kept(): void
    {
        $this->seed(RaviProductListSeeder::class);
        $product = Product::firstWhere('slug', 'ivory-royal-heritage-sharara-set');
        $product->update(['images' => ['Products/chosen-in-admin.jpg']]);

        $this->seed(RaviProductListSeeder::class);

        $this->assertSame(['Products/chosen-in-admin.jpg'], $product->fresh()->images);
    }

    /**
     * @return list<string>
     */
    private function seededSlugs(): array
    {
        $products = (new \ReflectionMethod(RaviProductListSeeder::class, 'products'))->invoke(app(RaviProductListSeeder::class));

        return array_column($products, 'slug');
    }
}
