<?php

namespace Tests\Feature;

use Carepeerinfotech\Ecommerce\Models\Product;
use Database\Seeders\RaviProductListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_pages_show_the_latest_products_first(): void
    {
        $this->seed(RaviProductListSeeder::class);

        // Neither id order matches the creation order, so only created_at can produce it.
        [$oldest, $newest, $middle] = Product::query()->orderBy('id')->take(3)->get()->all();
        Product::query()->update(['created_at' => now()->subYear()]);
        Product::query()->whereKey($oldest->id)->update(['created_at' => now()->subDays(3)]);
        Product::query()->whereKey($middle->id)->update(['created_at' => now()->subDays(2)]);
        Product::query()->whereKey($newest->id)->update(['created_at' => now()->subDay()]);

        foreach (['shop.search', 'shop.new-arrivals'] as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSeeInOrder(['data-id="'.$newest->id.'"', 'data-id="'.$middle->id.'"', 'data-id="'.$oldest->id.'"'], false);
        }
    }
}
