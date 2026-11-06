<?php

namespace App\Http\Controllers;

use App\View\Components\ProductCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Peer\CMS\Models\Category;
use Peer\Ecommerce\Models\CategoryMedia;
use Peer\Ecommerce\Models\Product;

/**
 * Listing pages render every matching product; public/assets/js/main.js then
 * filters, sorts and paginates them in the browser.
 */
class StorefrontController extends Controller
{
    /** Products added within this many days count as new arrivals. */
    public const NEW_ARRIVAL_DAYS = 30;

    private const HOME_SECTION_SIZE = 4;

    /** Top-level categories with their own listing page, each routed as "shop.{slug}". */
    public const CATEGORY_PAGES = ['girls', 'boys', 'men', 'women'];

    /** How many products from the same subcategory a product page suggests. */
    private const RELATED_PRODUCTS = 4;

    /** Link-preview images are scaled down to this width. */
    private const PREVIEW_WIDTH = 1200;

    /** The home page's "Shop for …" rows, in display order, keyed by top-level category slug. */
    private const HOME_SHOP_SECTIONS = [
        'girls' => 'Shop for Girls',
        'boys' => 'Shop for Boys',
        'men' => 'Shop for Men',
        'women' => 'Shop for Women',
    ];

    /** Each listing page's introduction. Here because the CMS's categories only hold a name and a parent. */
    private const CATEGORY_DESCRIPTIONS = [
        'girls' => 'Lehengas, sharara and palazzo suits, Patiala salwar suits, frocks and gowns for girls aged 1 to 14, from their first festive outfit to the big family wedding.',
        'boys' => 'Sherwanis, indo-westerns, coat suits, jodhpuris, kurta pajamas and waistcoat sets for boys aged 1 to 14, so the little ones match the baraat.',
        'men' => 'Kurta pajamas, waistcoat sets and indo-westerns for the groom, the baraat and every festive occasion in between.',
        'women' => 'Ethnic co-ord sets for women: hand-picked occasion wear from the heart of Amritsar.',
    ];

    /** Kids' rows are a carousel of category cards; the rest keep the intro panel and tile grid. */
    private const HOME_CAROUSEL_SECTIONS = ['girls', 'boys'];

    public function home(): View
    {
        return view('shop.home', [
            'shopSections' => $this->shopSections(),
            'newArrivals' => $this->newArrivalsQuery()->take(self::HOME_SECTION_SIZE)->get(),
            'bestsellers' => $this->productQuery()->featured()->take(self::HOME_SECTION_SIZE)->get(),
        ]);
    }

    public function men(): View
    {
        return $this->category('men');
    }

    public function women(): View
    {
        return $this->category('women');
    }

    public function boys(): View
    {
        return $this->category('boys');
    }

    public function girls(): View
    {
        return $this->category('girls');
    }

    public function newArrivals(): View
    {
        return view('shop.new-arrivals', ['products' => $this->newArrivalsQuery()->get()]);
    }

    public function search(): View
    {
        return view('shop.search', ['products' => $this->productQuery()->get()]);
    }

    /** The wishlist lives in the browser, so the page renders every product and the script shows the saved ones. */
    public function wishlist(): View
    {
        return view('shop.wishlist', ['products' => $this->productQuery()->get()]);
    }

    /** A product's own page; inactive products, or ones without a category, are not found. */
    public function product(string $slug): View
    {
        $product = $this->productQuery()->where('slug', $slug)->firstOrFail();

        $card = new ProductCard($product);

        return view('shop.product', [
            'product' => $product,
            'card' => $card,
            'categoryUrl' => in_array($card->categorySlug, self::CATEGORY_PAGES, true) ? route('shop.'.$card->categorySlug) : null,
            'gallery' => $product->galleryFor(),
            'related' => $this->productQuery()
                ->whereHas('categories', fn (Builder $categories) => $categories->whereKey($product->categories->modelKeys()))
                ->whereKeyNot($product->id)
                ->take(self::RELATED_PRODUCTS)
                ->get(),
        ]);
    }

    /**
     * The product's photo as a JPEG at most 1200px wide, for link previews: WhatsApp skips
     * WebP and large images and falls back to the site logo, so the page's og:image points here.
     */
    public function productPreview(string $slug): Response
    {
        $product = $this->productQuery()->where('slug', $slug)->firstOrFail();
        $gallery = $product->galleryFor();
        $file = $this->localImagePath($gallery['images'][$gallery['active']] ?? null);
        $image = $file ? @imagecreatefromstring((string) file_get_contents($file)) : false;
        abort_if($image === false, 404);

        $width = imagesx($image);
        if ($width > self::PREVIEW_WIDTH) {
            $image = imagescale($image, self::PREVIEW_WIDTH);
        }

        // JPEG has no transparency, so a transparent photo gets a white background.
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        ob_start();
        imagejpeg($canvas, null, 82);
        $jpeg = (string) ob_get_clean();

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /** The file behind a photo URL on this site, e.g. /storage/products/x.webp or /assets/images/x.jpg. */
    private function localImagePath(?string $url): ?string
    {
        $path = ltrim((string) parse_url((string) $url, PHP_URL_PATH), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $candidates = [public_path($path)];
        if (str_starts_with($path, 'storage/')) {
            $candidates[] = Storage::disk('public')->path(substr($path, strlen('storage/')));
        }

        return collect($candidates)->first(fn (string $file): bool => is_file($file));
    }

    private function category(string $slug): View
    {
        $category = Category::query()
            ->whereNull('parent_id')
            ->where('slug', $slug)
            ->with(['children' => fn ($children) => $children->orderBy('id')])
            ->firstOrFail();

        return view('shop.category', [
            'category' => $category,
            'categoryDescription' => self::CATEGORY_DESCRIPTIONS[$slug],
            'products' => $this->productQuery()->inCategoryTree($category->id)->get(),
        ]);
    }

    /**
     * Each top-level category, introduced by its name and description, with its
     * subcategories as tiles. A tile links to its parent's listing with the
     * subcategory filter preselected.
     *
     * @return list<array{title: string, name: string, description: ?string, url: string, layout: 'carousel'|'band', tiles: list<array{name: string, image: ?string, url: string}>}>
     */
    private function shopSections(): array
    {
        $parents = Category::query()
            ->whereNull('parent_id')
            ->whereIn('slug', array_keys(self::HOME_SHOP_SECTIONS))
            ->with(['children' => fn ($children) => $children->orderBy('id')])
            ->get()
            ->keyBy('slug');

        $images = CategoryMedia::query()
            ->whereIn('category_id', $parents->flatMap->children->pluck('id'))
            ->where('mime_type', 'like', 'image/%')
            ->ordered()
            ->get()
            ->unique('category_id')
            ->keyBy('category_id');

        $sections = [];

        foreach (self::HOME_SHOP_SECTIONS as $slug => $title) {
            $parent = $parents->get($slug);

            if ($parent === null || $parent->children->isEmpty()) {
                continue;
            }

            $parentUrl = route('shop.'.$slug);

            $sections[] = [
                'title' => $title,
                'name' => $parent->name,
                'description' => self::CATEGORY_DESCRIPTIONS[$slug],
                'url' => $parentUrl,
                'layout' => in_array($slug, self::HOME_CAROUSEL_SECTIONS, true) ? 'carousel' : 'band',
                'tiles' => $parent->children->map(fn (Category $subcategory): array => [
                    'name' => $subcategory->name,
                    'image' => $images->get($subcategory->id)?->url(),
                    'url' => $parentUrl.'#'.ProductCard::subcategoryFilter($subcategory->name),
                ])->all(),
            ];
        }

        return $sections;
    }

    /**
     * @return Builder<Product>
     */
    private function newArrivalsQuery(): Builder
    {
        return $this->productQuery()
            ->where('created_at', '>=', now()->subDays(self::NEW_ARRIVAL_DAYS));
    }

    /**
     * Active products that have a category, with what a product card needs, newest first.
     *
     * @return Builder<Product>
     */
    private function productQuery(): Builder
    {
        return Product::query()
            ->active()
            ->has('categories')
            ->with([
                'categories.parent',
                'variants' => fn ($variants) => $variants->orderBy('id'),
            ])
            ->latest()
            ->orderByDesc('id');
    }
}
