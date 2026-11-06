<?php

namespace Tests\Feature;

use App\View\Components\ProductCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Peer\CMS\Models\Category;
use Tests\Concerns\BuildsStorefrontCatalog;
use Tests\TestCase;

class StorefrontMenuTest extends TestCase
{
    use BuildsStorefrontCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCategoryTree();
    }

    public function test_header_menu_lists_top_level_categories_with_their_subcategories(): void
    {
        $menu = $this->headerMenu(route('shop.search'));

        $this->assertSame(['Girls', 'Boys', 'Men', 'Women'], $this->headings($menu));
        $this->assertStringContainsString('<a href="'.route('shop.girls').'#lehenga">Lehenga</a>', $menu);
        $this->assertStringContainsString('<a href="'.route('shop.girls').'#frocks-gowns">Frocks &amp; Gowns</a>', $menu);
        $this->assertStringContainsString('<a href="'.route('shop.boys').'#coat-suits-tuxedos">Coat Suits / Tuxedos</a>', $menu);
        $this->assertStringContainsString('<a href="'.route('shop.men').'#kurta-pajamas">Kurta Pajamas</a>', $menu);
        $this->assertStringContainsString('<a href="'.route('shop.women').'#co-ord-sets">Co-ord Sets</a>', $menu);
        $this->assertStringContainsString('<a class="all" href="'.route('shop.boys').'">Shop all Boys →</a>', $menu);
        $this->assertStringContainsString('<a href="'.route('shop.new-arrivals').'">New Arrivals</a>', $menu);
    }

    public function test_mobile_menu_lists_the_same_categories(): void
    {
        $menu = Str::betweenFirst($this->get(route('shop.search'))->assertOk()->getContent(), '<div id="mnavList">', 'class="mnav-help"');

        $this->assertStringContainsString('<summary>Girls</summary>', $menu);
        $this->assertStringContainsString('<a href="'.route('shop.women').'">All Women</a>', $menu);
        $this->assertStringContainsString('<a href="'.route('shop.boys').'#jodhpuri">Jodhpuri</a>', $menu);
        $this->assertStringContainsString('<a href="'.route('shop.wishlist').'">Wishlist</a>', $menu);
    }

    public function test_menu_reflects_category_changes(): void
    {
        $this->subcategory('Boys', 'Jodhpuri')->delete();
        Category::firstWhere('slug', 'men')->update(['name' => 'Gents']);
        $this->subcategory('Girls', 'Lehenga')->update(['name' => 'Lehenga Choli']);

        $menu = $this->headerMenu(route('shop.search'));

        $this->assertSame(['Girls', 'Boys', 'Women'], $this->headings($menu));
        $this->assertStringNotContainsString('#jodhpuri', $menu);
        $this->assertStringContainsString('<a href="'.route('shop.girls').'#lehenga-choli">Lehenga Choli</a>', $menu);
    }

    public function test_top_level_category_without_its_own_page_is_left_off_the_menu(): void
    {
        Category::create(['name' => 'Accessories']);

        $this->assertSame(['Girls', 'Boys', 'Men', 'Women'], $this->headings($this->headerMenu(route('shop.search'))));
    }

    public function test_current_category_is_marked_active(): void
    {
        $menu = $this->headerMenu(route('shop.boys'));

        $this->assertStringContainsString('<a href="'.route('shop.boys').'" class="active">Boys</a>', $menu);
        $this->assertStringNotContainsString('<a href="'.route('shop.girls').'" class="active">', $menu);
    }

    public function test_a_subcategory_named_in_punjabi_still_gets_its_own_filter(): void
    {
        $this->assertNotSame('', ProductCard::subcategoryFilter('ਲਹਿੰਗਾ'));
        $this->assertSame(ProductCard::subcategoryFilter('ਲਹਿੰਗਾ'), ProductCard::subcategoryFilter('ਲਹਿੰਗਾ'));
        $this->assertNotSame(ProductCard::subcategoryFilter('ਲਹਿੰਗਾ'), ProductCard::subcategoryFilter('ਸ਼ਰਾਰਾ'));
    }

    /** The desktop mega menu's markup on the given page. */
    private function headerMenu(string $url): string
    {
        return Str::betweenFirst($this->get($url)->assertOk()->getContent(), '<ul id="mega">', '</ul>');
    }

    /**
     * @return list<string>
     */
    private function headings(string $menu): array
    {
        preg_match_all('/<h4>(.*?)<\/h4>/', $menu, $matches);

        return $matches[1];
    }
}
