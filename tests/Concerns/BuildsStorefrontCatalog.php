<?php

namespace Tests\Concerns;

use Database\Seeders\StorefrontCategorySeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Peer\CMS\Models\Category;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Services\Catalog\ProductCatalogService;

/**
 * Builds just the catalog a storefront test needs, on the real category tree, with
 * the media library faked so nothing is written to public/media.
 */
trait BuildsStorefrontCatalog
{
    protected function seedCategoryTree(): void
    {
        Storage::fake(config('cms.media.disk'));

        $this->seed(StorefrontCategorySeeder::class);
    }

    protected function subcategory(string $parentName, string $name): Category
    {
        return Category::query()
            ->where('name', $name)
            ->whereHas('parent', fn ($parent) => $parent->where('name', $parentName))
            ->firstOrFail();
    }

    /**
     * A product sold in the given sizes. Its SKU is the code plus the default size,
     * the way the store's own products are, e.g. "RE-0035-S".
     *
     * @param  list<string>  $sizes
     * @param  array<string, mixed>  $attributes
     */
    protected function makeProduct(string $name, Category $category, string $code, int $price, array $sizes = ['S', 'M', 'L'], array $attributes = []): Product
    {
        $comparePrice = $attributes['compare_price'] ?? null;

        return app(ProductCatalogService::class)->save(
            new Product,
            [
                'category_ids' => [$category->id],
                'name' => $name,
                'slug' => Str::slug($name),
                'sku' => $code,
                'price' => $price,
                'stock' => 10,
                'is_active' => true,
                'images' => ['Products/'.Str::slug($name).'.webp'],
                ...$attributes,
            ],
            [['name' => 'Size', 'values' => $sizes]],
            array_map(fn (string $size, int $position): array => [
                'sku' => $code.'-'.$size,
                'name' => $name.' '.$size,
                'option_label' => $size,
                'option_values' => ['Size' => $size],
                'price' => $price,
                'compare_price' => $comparePrice,
                'stock' => 10,
                'is_default' => $position === 0,
            ], $sizes, array_keys($sizes)),
            defaultVariantLabel: $sizes[0],
        );
    }
}
