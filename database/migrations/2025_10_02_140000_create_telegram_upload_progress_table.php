<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8.2: Progress Tracking Migration
 * 
 * جدول برای ذخیره progress آپلود فایل‌ها از URL
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('telegram_upload_progress', function (Blueprint $table) {
            $table->id();
            
            // Session & User
            $table->string('session_id', 64)->unique()->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // File Information
            $table->text('url');
            $table->string('filename');
            $table->bigInteger('total_size')->nullable()->comment('Total file size in bytes');
            
            // Progress Tracking
            $table->bigInteger('downloaded_bytes')->default(0)->comment('Downloaded from URL');
            $table->bigInteger('uploaded_bytes')->default(0)->comment('Uploaded to Telegram');
            $table->decimal('download_speed', 15, 2)->nullable()->comment('Download speed in bytes/sec');
            $table->decimal('upload_speed', 15, 2)->nullable()->comment('Upload speed in bytes/sec');
            $table->integer('download_eta')->nullable()->comment('Download ETA in seconds');
            $table->integer('upload_eta')->nullable()->comment('Upload ETA in seconds');
            
            // Status & Result
            $table->enum('status', [
                'pending',
                'downloading',
                'uploading',
                'completed',
                'failed',
                'cancelled'
            ])->default('pending')->index();
            
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable()->comment('Extra data');
            
            // File Entry (after completion)
            $table->unsignedInteger('file_entry_id')->nullable()->index();
            $table->foreign('file_entry_id')->references('id')->on('file_entries')->onDelete('set null');
            
            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_upload_progress');
    }
};