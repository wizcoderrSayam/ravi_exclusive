<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Start the admin's WhatsApp number setting at the number the storefront already
     * used, so the shop's links and WhatsApp orders carry on unchanged after deploying.
     * This lives in the app because the number is this store's, not the package's.
     */
    public function up(): void
    {
        $now = now();

        DB::table('settings')->insertOrIgnore([
            'group' => 'commerce',
            'key' => 'whatsapp_number',
            'value' => '+91-814-814-62-62',
            'type' => 'string',
            'is_encrypted' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Cache::forget('settings.group.commerce');
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'commerce')
            ->where('key', 'whatsapp_number')
            ->delete();

        Cache::forget('settings.group.commerce');
    }
};
