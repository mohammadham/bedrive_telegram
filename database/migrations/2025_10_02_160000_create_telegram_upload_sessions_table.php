<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8.3: Resume Upload - Sessions Migration
 * 
 * جدول برای ذخیره state آپلودهای chunked
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('telegram_upload_sessions', function (Blueprint $table) {
            $table->id();
            
            // Session & User
            $table->string('session_id', 64)->unique()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('progress_id')->nullable()->constrained('telegram_upload_progress')->onDelete('cascade');
            
            // File Information
            $table->text('url');
            $table->string('filename');
            $table->bigInteger('total_size')->nullable()->comment('Total file size in bytes');
            $table->string('temp_path')->nullable()->comment('Temporary file path');
            
            // Chunking Configuration
            $table->integer('chunk_size')->default(5242880)->comment('Chunk size in bytes (5MB default)');
            $table->integer('total_chunks')->default(0)->comment('Total number of chunks');
            $table->integer('completed_chunks')->default(0)->comment('Number of completed chunks');
            
            // Download State
            $table->json('chunks_map')->nullable()->comment('Map of completed chunks');
            $table->bigInteger('downloaded_bytes')->default(0)->comment('Total downloaded bytes');
            
            // Upload State (to Telegram)
            $table->bigInteger('uploaded_bytes')->default(0)->comment('Uploaded to Telegram');
            $table->string('telegram_file_id')->nullable()->comment('Telegram file ID after upload');
            
            // Status & Control
            $table->enum('status', [
                'initialized',
                'downloading',
                'paused',
                'downloaded',
                'uploading',
                'completed',
                'failed',
                'cancelled'
            ])->default('initialized')->index();
            
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable()->comment('Extra session data');
            
            // Resume Control
            $table->boolean('is_resumable')->default(true)->comment('Can be resumed');
            $table->timestamp('last_activity_at')->nullable()->comment('Last download/upload activity');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['status', 'is_resumable']);
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_upload_sessions');
    }
};
