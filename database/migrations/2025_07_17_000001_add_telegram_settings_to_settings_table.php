<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add telegram storage settings to the settings table
        // Note: BeDrive uses a JSON-based settings system, so we don't need to modify the schema
        // The settings will be stored in the existing settings table as JSON values

        // Insert default telegram settings if they don't exist
        //  if (Schema::hasTable('settings')) {
        // $defaultSettings = [
        //     'storage_telegram_api_id' => '',
        //     'storage_telegram_api_hash' => '',
        //     'storage_telegram_phone' => '',
        //     'storage_telegram_chat_id' => '',
        // ];

        // foreach ($defaultSettings as $key => $value) {
        //     DB::table('settings')->updateOrInsert(
        //         ['name' => $key],
        //         ['value' => $value]
        //     );
        //     }
        // }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove telegram settings
        $telegramSettings = [
            'storage_telegram_api_id',
            'storage_telegram_api_hash',
            'storage_telegram_phone',
            'storage_telegram_chat_id',
        ];

        DB::table('settings')->whereIn('name', $telegramSettings)->delete();
    }
};

