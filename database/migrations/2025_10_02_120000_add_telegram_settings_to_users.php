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
        Schema::table('users', function (Blueprint $table) {
            // تنظیمات Auto-forward تلگرام
            $table->boolean('telegram_auto_forward')->default(false)->after('email_verified_at');
            $table->string('telegram_forward_target', 100)->nullable()->after('telegram_auto_forward');
            
            // Index برای query سریع
            $table->index(['telegram_auto_forward', 'telegram_forward_target'], 'users_telegram_forward_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_telegram_forward_idx');
            $table->dropColumn(['telegram_auto_forward', 'telegram_forward_target']);
        });
    }
};
