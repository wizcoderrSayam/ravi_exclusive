<?php

namespace Tests\Feature;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Database\Seeders\EcommerceSeeder;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\Role;
use Carepeerinfotech\Ecommerce\Models\Setting;
use Carepeerinfotech\Ecommerce\Services\Settings\SettingsService;
use Database\Seeders\RaviProductListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminStoreWhatsAppNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_settings_page_starts_with_the_stores_existing_number(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('WhatsApp number')
            ->assertSee('value="+91-814-814-62-62"', false);
    }

    public function test_an_admin_can_change_the_number(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.update'), $this->commerceSettings('+44 7700 900123'))
            ->assertOk();

        $this->assertSame('+44 7700 900123', app(SettingsService::class)->get('commerce', 'whatsapp_number'));
    }

    #[DataProvider('invalidNumbers')]
    public function test_an_unusable_number_is_rejected_and_the_old_one_is_kept(string $number): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.update'), $this->commerceSettings($number))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('whatsapp_number');

        $this->assertSame('+91-814-814-62-62', app(SettingsService::class)->get('commerce', 'whatsapp_number'));
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
        $this->seed(RaviProductListSeeder::class);
        // Seeded descriptions quote the number as plain text, which the setting does not rewrite.
        Product::query()->update(['description' => '']);
        app(SettingsService::class)->set('commerce', 'whatsapp_number', '+44 7700 900123');

        $home = $this->get(route('shop.home'));
        $home->assertOk();
        $home->assertSee('data-whatsapp="447700900123"', false);
        $home->assertSee('href="https://wa.me/447700900123"', false);
        $home->assertSee('class="wa-float" href="https://wa.me/447700900123?text=', false);
        $home->assertSee('href="tel:+447700900123"', false);
        $home->assertSee('+44 7700 900123');
        $home->assertDontSee('8148146262');
        $home->assertDontSee('+91-814-814-62-62');

        $productPage = $this->get(route('shop.product', Product::query()->firstOrFail()->slug));
        $productPage->assertOk();
        $productPage->assertSee('<a href="tel:+447700900123" class="text-red">+44 7700 900123</a>', false);
        $productPage->assertDontSee('8148146262');
        $productPage->assertDontSee('+91-814-814-62-62');
    }

    public function test_the_storefront_keeps_a_working_number_when_the_setting_is_missing(): void
    {
        Setting::query()->where('group', 'commerce')->where('key', 'whatsapp_number')->delete();
        Cache::forget('settings.group.commerce');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('data-whatsapp="918148146262"', false)
            ->assertSee('href="https://wa.me/918148146262"', false)
            ->assertSee('href="tel:+918148146262"', false);
    }

    private function admin(): User
    {
        $this->seed(EcommerceSeeder::class);

        // fresh() loads is_active's database default, which the admin middleware checks.
        $admin = User::factory()->create()->fresh();
        $admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));

        return $admin;
    }

    /**
     * @return array{group: string, country: string, store_name: string, support_email: string, gst_percent: int, pickup_pincode: string, whatsapp_number: string}
     */
    private function commerceSettings(string $whatsappNumber): array
    {
        return [
            'group' => 'commerce',
            'country' => 'IN',
            'store_name' => 'Ravi Exclusive',
            'support_email' => 'care@example.com',
            'gst_percent' => 18,
            'pickup_pincode' => '143001',
            'whatsapp_number' => $whatsappNumber,
        ];
    }
}
