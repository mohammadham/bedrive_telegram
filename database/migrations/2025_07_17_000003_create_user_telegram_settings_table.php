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
        Schema::create('user_telegram_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('telegram_user_chat_id')->nullable();
            $table->boolean('telegram_auto_forward')->default(false);
            $table->timestamps();

            $table->unique('user_id');

               // اضافه کردن FK بعد از ایجاد جدول
        $table->foreign('user_id')
        ->references('id')
        ->on('users')
        ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_telegram_settings');
    }
};

