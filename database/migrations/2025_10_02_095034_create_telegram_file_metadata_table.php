<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for Telegram file metadata storage
 * 
 * This table stores Telegram-specific information for files uploaded via Telegram driver
 * Supports both Bot API and User Account (MTProto) uploads
 */
class CreateTelegramFileMetadataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('telegram_file_metadata', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to file_entries table
            // Note: file_entries uses integer (not bigInteger), so we must match that type
            $table->unsignedInteger('file_entry_id')->unique();
            $table->foreign('file_entry_id')
                  ->references('id')
                  ->on('file_entries')
                  ->onDelete('cascade');
            
            // Telegram file identifiers
            $table->string('telegram_file_id', 255)->nullable()->index();
            $table->string('telegram_file_unique_id', 255)->nullable();
            
            // Message information
            $table->bigInteger('message_id')->nullable()->index();
            $table->string('channel_id', 100)->nullable()->index();
            
            // Upload method tracking
            $table->enum('upload_method', ['bot', 'user'])
                  ->default('bot')
                  ->index()
                  ->comment('bot: Bot API (< 50MB), user: User Account MTProto (up to 2GB)');
            
            // File metadata
            $table->bigInteger('original_file_size')->unsigned()->nullable();
            $table->string('original_mime_type', 100)->nullable();
            $table->string('telegram_mime_type', 100)->nullable();
            
            // Upload session information
            $table->string('session_file', 255)->nullable()
                  ->comment('Path to MadelineProto session file (for user uploads)');
            
            // Telegram-specific file type
            $table->enum('telegram_file_type', [
                'document',
                'photo', 
                'video',
                'audio',
                'voice',
                'video_note',
                'animation'
            ])->default('document');
            
            // Upload status and metadata
            $table->enum('upload_status', [
                'pending',
                'uploading',
                'completed',
                'failed',
                'deleted'
            ])->default('pending')->index();
            
            $table->text('error_message')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            
            // Additional metadata (JSON)
            $table->json('metadata')->nullable()
                  ->comment('Additional Telegram-specific metadata');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['upload_method', 'upload_status']);
            $table->index(['channel_id', 'message_id']);
            $table->index('uploaded_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('telegram_file_metadata');
    }
}
