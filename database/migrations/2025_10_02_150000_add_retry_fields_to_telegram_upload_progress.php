<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8.4: Auto-Retry System Migration
 * 
 * افزودن ستون‌های مورد نیاز برای retry logic
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('telegram_upload_progress', function (Blueprint $table) {
            // Retry tracking
            $table->unsignedTinyInteger('retry_count')->default(0)->after('status')->comment('Number of retry attempts');
            $table->unsignedTinyInteger('max_retries')->default(3)->after('retry_count')->comment('Maximum retry attempts');
            $table->timestamp('last_retry_at')->nullable()->after('max_retries')->comment('Last retry timestamp');
            $table->timestamp('next_retry_at')->nullable()->after('last_retry_at')->comment('Next retry scheduled time');
            $table->boolean('is_retryable')->default(true)->after('next_retry_at')->comment('Can be retried');
            $table->string('retry_phase')->nullable()->after('is_retryable')->comment('download or upload');
            
            // Index برای retry queue
            $table->index(['is_retryable', 'retry_count', 'next_retry_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_upload_progress', function (Blueprint $table) {
            $table->dropColumn([
                'retry_count',
                'max_retries',
                'last_retry_at',
                'next_retry_at',
                'is_retryable',
                'retry_phase',
            ]);
        });
    }
};
