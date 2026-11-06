<?php

namespace App\Providers;

use App\Http\Controllers\StorefrontController;
use App\Models\User;
use App\View\Components\ProductCard;
use App\View\StoreWhatsApp;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Peer\CMS\Models\Category;
use Peer\Ecommerce\Contracts\SettingsRepository;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Support\Permissions;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped rather than once(): long-lived workers must rebuild the menu for each request or job.
        $this->app->scoped('storefront.menu', fn (): array => $this->menuCategories());
        $this->app->scoped('storefront.whatsapp', fn (): StoreWhatsApp => new StoreWhatsApp(
            (string) $this->app->make(SettingsRepository::class)->get('general.whatsapp_number', StoreWhatsApp::ORIGINAL_NUMBER)
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('accessCms', fn (User $user): bool => $user->canSignIn() && $this->usesCmsPanel($user));

        // Must start with + and the country code, or wa.me links would reach the wrong number.
        $this->app->make(SettingsRepository::class)->register('general', 'General', [
            'whatsapp_number' => [
                'label' => 'WhatsApp number',
                'type' => 'text',
                'default' => StoreWhatsApp::ORIGINAL_NUMBER,
                'placeholder' => '+91 98765 43210',
                'help' => 'Include the country code. Customers see this number on the shop, and their WhatsApp orders go to it.',
                'rules' => ['required', 'regex:/^\+\d(?:[ -]?\d){7,14}\z/'],
            ],
        ]);

        // The header's search box lives on every page, so it gets the catalogue index via a composer rather than each controller.
        View::composer('partials.header', fn ($view) => $view->with('searchIndex', $this->searchIndex()));

        // The desktop mega menu and the mobile menu list the same categories, so both share one query per request.
        View::composer(['partials.header', 'partials.mobile-nav'], fn ($view) => $view->with('menuCategories', $this->app->make('storefront.menu')));

        View::composer(
            ['layouts.app', 'partials.header', 'partials.mobile-nav', 'partials.footer', 'shop.home', 'shop.product'],
            fn ($view) => $view->with('storeWhatsApp', $this->app->make('storefront.whatsapp'))
        );
    }

    /**
     * Whether the user's role has anything to do in the CMS's panel: one of its screens,
     * or the product form, whose image and category pickers are the CMS's. Some CMS
     * routes, such as media uploads, have no permission of their own, so a role with
     * neither must not pass the panel's gate.
     */
    private function usesCmsPanel(User $user): bool
    {
        return collect($this->app->make(Permissions::class)->groups())
            ->only(['CMS', 'Backups', 'Docs', 'Settings'])
            ->flatMap(fn (array $group): array => array_keys($group['permissions']))
            ->push('products.manage')
            ->contains(fn (string $permission): bool => $user->hasPermission($permission));
    }

    /**
     * Each top-level category that has its own listing page, in display order, with
     * links to its subcategories. A subcategory link opens the parent's listing with
     * that subcategory's filter preselected.
     *
     * @return list<array{name: string, routeName: string, url: string, links: list<array{name: string, url: string}>}>
     */
    private function menuCategories(): array
    {
        return Category::query()
            ->whereNull('parent_id')
            ->whereIn('slug', StorefrontController::CATEGORY_PAGES)
            ->with(['children' => fn ($children) => $children->orderBy('id')])
            ->get()
            ->sortBy(fn (Category $category): int => array_search($category->slug, StorefrontController::CATEGORY_PAGES, true))
            ->values()
            ->map(function (Category $category): array {
                $url = route('shop.'.$category->slug);

                return [
                    'name' => $category->name,
                    'routeName' => 'shop.'.$category->slug,
                    'url' => $url,
                    'links' => $category->children->map(fn (Category $subcategory): array => [
                        'name' => $subcategory->name,
                        'url' => $url.'#'.ProductCard::subcategoryFilter($subcategory->name),
                    ])->all(),
                ];
            })
            ->all();
    }

    /**
     * What the header's instant-search box filters client-side: just enough about
     * each active product that has a category, to list and link to it.
     *
     * @return list<array{name: string, url: string, image: ?string, price: string, search: string}>
     */
    private function searchIndex(): array
    {
        return Product::query()
            ->active()
            ->has('categories')
            ->with(['categories.parent', 'variants' => fn ($variants) => $variants->orderBy('id')])
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
