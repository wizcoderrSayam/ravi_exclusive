<?php

namespace Tests\Feature;

use App\Models\User;
use App\View\StoreWhatsApp;
use Database\Seeders\RaviProductListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Peer\Ecommerce\Database\Seeders\EcommerceSeeder;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Models\Role;
use Peer\Ecommerce\Support\StoreSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminStoreWhatsAppNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_setting_starts_with_the_stores_original_number(): void
    {
        $this->assertSame('+91-814-814-62-62', StoreSettings::get('general.whatsapp_number'));
    }

    public function test_the_field_is_in_the_cms_general_settings(): void
    {
        $this->actingAs($this->admin())
            ->get(route('cms.panel.settings.index'))
            ->assertOk()
            ->assertSee('WhatsApp number');
    }

    public function test_an_admin_can_change_the_number(): void
    {
        $this->actingAs($this->admin())
            ->putJson(route('cms.panel.settings.update'), ['general' => ['whatsapp_number' => '+44 7700 900123']])
            ->assertOk();

        $this->assertSame('+44 7700 900123', StoreSettings::get('general.whatsapp_number'));
    }

    #[DataProvider('invalidNumbers')]
    public function test_an_unusable_number_is_rejected_and_the_old_one_is_kept(string $number): void
    {
        $this->actingAs($this->admin())
            ->putJson(route('cms.panel.settings.update'), ['general' => ['whatsapp_number' => $number]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('general.whatsapp_number');

        $this->assertSame('+91-814-814-62-62', StoreSettings::get('general.whatsapp_number'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'blank' => [''],
            'no country code' => ['8148146262'],
            'too short' => ['+91 81481'],
            'too long' => ['+91 81481 46262 81481 46262'],
            'letters' => ['+91 call-us-now'],
        ];
    }

    public function test_the_storefront_uses_the_saved_number_everywhere(): void
    {
        Storage::fake(config('cms.media.disk'));
        $this->seed(RaviProductListSeeder::class);
        StoreSettings::set('general.whatsapp_number', '+44 7700 900123');

        // The seeded descriptions quote the original number, so this covers them too.
        $product = Product::query()->where('description', 'like', '%'.StoreWhatsApp::ORIGINAL_NUMBER.'%')->firstOrFail();

        $home = $this->get(route('shop.home'));
        $home->assertOk();
        $home->assertSee('data-whatsapp="447700900123"', false);
        $home->assertSee('href="https://wa.me/447700900123"', false);
        $home->assertSee('class="wa-float" href="https://wa.me/447700900123?text=', false);
        $home->assertSee('href="tel:+447700900123"', false);
        $home->assertSee('+44 7700 900123');
        $home->assertDontSee('8148146262');
        $home->assertDontSee(StoreWhatsApp::ORIGINAL_NUMBER);

        $productPage = $this->get(route('shop.product', $product->slug));
        $productPage->assertOk();
        $productPage->assertSee('<a href="tel:+447700900123" class="text-red">+44 7700 900123</a>', false);
        $productPage->assertSee('WhatsApp us on +44 7700 900123');
        $productPage->assertDontSee('8148146262');
        $productPage->assertDontSee(StoreWhatsApp::ORIGINAL_NUMBER);
    }

    private function admin(): User
    {
        $this->seed(EcommerceSeeder::class);

        // fresh() loads is_active's database default, which the admin middleware checks.
        $admin = User::factory()->create()->fresh();
        $admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));

        return $admin;
    }
}
