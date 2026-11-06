<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Peer\Ecommerce\Database\Seeders\EcommerceSeeder;
use Peer\Ecommerce\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Every seeder here is safe to re-run.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
        ]);

        // Only on a fresh install; afterwards, roles and email templates belong to the
        // admin. Checks for this role because the package's migrations create others.
        if (! Role::query()->where('slug', 'super-admin')->exists()) {
            $this->call(EcommerceSeeder::class);
        }

        $this->call([
            StorefrontCategorySeeder::class,
            RaviProductListSeeder::class,
            CmsPageSeeder::class,
        ]);
    }
}
