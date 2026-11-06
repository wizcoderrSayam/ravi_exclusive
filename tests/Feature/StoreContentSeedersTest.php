<?php

namespace Tests\Feature;

use Database\Seeders\CmsPageSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Peer\CMS\Models\Page;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Models\Role;
use Tests\TestCase;

class StoreContentSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_pages_publish_store_info_and_draft_the_policies(): void
    {
        $this->seed(CmsPageSeeder::class);

        $this->assertEqualsCanonicalizing(['about-us', 'contact-us'], Page::published()->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(
            ['shipping-policy', 'returns-and-exchanges', 'privacy-policy', 'terms-and-conditions'],
            Page::where('status', 'draft')->pluck('slug')->all(),
        );
        $this->assertStringContainsString(' 22, Katra Jaimal Singh, Amritsar', Page::firstWhere('slug', 'contact-us')->html);
        $this->assertStringContainsString('Orders above ₹999 ship free', Page::firstWhere('slug', 'shipping-policy')->html);
        $this->assertStringContainsString('href="tel:+919915018282"', Page::firstWhere('slug', 'contact-us')->html);
        $this->assertStringContainsString('href="https://wa.me/919915018282"', Page::firstWhere('slug', 'contact-us')->html);
    }

    public function test_cms_page_seeder_keeps_edits_made_in_the_cms(): void
    {
        $this->seed(CmsPageSeeder::class);
        Page::where('slug', 'about-us')->update(['html' => '<p>Edited in the CMS</p>']);

        $this->seed(CmsPageSeeder::class);

        $this->assertSame(6, Page::count());
        $this->assertSame('<p>Edited in the CMS</p>', Page::firstWhere('slug', 'about-us')->html);
    }

    public function test_database_seeder_builds_the_whole_store_and_can_run_again(): void
    {
        Storage::fake(config('cms.media.disk'));

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Role::where('slug', 'super-admin')->exists());
        $this->assertSame(49, Product::count());
        $this->assertSame(6, Page::count());
    }
}
