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
            if (!Schema::hasColumn('users', 'telegram_user_chat_id')) {
                $table->string('telegram_user_chat_id')->nullable()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'telegram_auto_forward')) {
                $table->boolean('telegram_auto_forward')->default(false)->after('telegram_user_chat_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_user_chat_id', 'telegram_auto_forward']);
        });
    }
};
