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
        Schema::create('telegram_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('file_entry_id');
            $table->string('telegram_file_id');
            $table->string('telegram_chat_id');
            $table->timestamps();

            $table->unique('file_entry_id');

            $table->foreign('file_entry_id')
                ->references('id')
                ->on('file_entries')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_files');
    }
};
