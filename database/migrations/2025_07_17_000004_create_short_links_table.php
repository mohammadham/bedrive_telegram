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
        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->string('short_code', 10)->unique();
            $table->unsignedInteger('file_entry_id');
            $table->unsignedInteger('user_id');
            $table->string('password')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('max_downloads')->nullable();
            $table->integer('download_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('access_log')->nullable();
            $table->timestamps();

            $table->index(['short_code', 'is_active']);
            $table->index(['user_id', 'created_at']);
            $table->index(['expires_at']);

    // Foreign keys
    $table->foreign('file_entry_id')->references('id')->on('file_entries')->onDelete('cascade');
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};

