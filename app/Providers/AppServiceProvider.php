<?php

namespace App\Providers;

use App\Http\Controllers\StorefrontController;
use App\View\Components\ProductCard;
use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped rather than once(): long-lived workers must rebuild the menu for each request or job.
        $this->app->scoped('storefront.menu', fn (): array => $this->menuCategories());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The header's search box lives on every page, so it gets the catalogue index via a composer rather than each controller.
        View::composer('partials.header', fn ($view) => $view->with('searchIndex', $this->searchIndex()));

        // The desktop mega menu and the mobile menu list the same categories, so both share one query per request.
        View::composer(['partials.header', 'partials.mobile-nav'], fn ($view) => $view->with('menuCategories', $this->app->make('storefront.menu')));
    }

    /**
     * Each active top-level category that has its own listing page, in display order,
     * with links to its active subcategories. A subcategory link opens the parent's
     * listing with that subcategory's filter preselected.
     *
     * @return list<array{name: string, routeName: string, url: string, links: list<array{name: string, url: string}>}>
     */
    private function menuCategories(): array
    {
        return Category::query()
            ->active()
            ->topLevel()
            ->whereIn('slug', StorefrontController::CATEGORY_PAGES)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with(['children' => fn ($children) => $children->active()])
            ->get()
            ->map(function (Category $category): array {
                $url = route('shop.'.$category->slug);

                return [
                    'name' => $category->name,
                    'routeName' => 'shop.'.$category->slug,
                    'url' => $url,
                    'links' => $category->children->map(fn (Category $subcategory): array => [
                        'name' => $subcategory->name,
                        'url' => $url.'#'.Str::after($subcategory->slug, $category->slug.'-'),
                    ])->all(),
                ];
            })
            ->all();
    }

    /**
     * What the header's instant-search box filters client-side: just enough about
     * each active product (in an active category) to list and link to it.
     *
     * @return list<array{name: string, url: string, image: ?string, price: string, search: string}>
     */
    private function searchIndex(): array
    {
        return Product::query()
            ->active()
            ->whereHas('category', fn (Builder $category) => $category->active())
            ->with(['category.parent', 'images', 'variants' => fn ($variants) => $variants->orderBy('id')])
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->map(function (Product $product): array {
                $card = new ProductCard($product);

                return [
                    'name' => $product->name,
                    'url' => $card->url,
                    'image' => $card->imageUrl,
                    'price' => $card->money($card->price),
                    'search' => $card->searchText,
                ];
            })
            ->all();
    }
}
