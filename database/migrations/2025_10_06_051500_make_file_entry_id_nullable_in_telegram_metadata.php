<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make file_entry_id nullable in telegram_file_metadata
 * 
 * This fixes the issue where metadata is created before FileEntry exists.
 * The FileEntry will be linked later via an event listener.
 */
class MakeFileEntryIdNullableInTelegramMetadata extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('telegram_file_metadata', function (Blueprint $table) {
            // Drop existing foreign key first
            $table->dropForeign(['file_entry_id']);
            
            // Drop unique constraint
            $table->dropUnique(['file_entry_id']);
            
            // Make file_entry_id nullable
            $table->unsignedInteger('file_entry_id')->nullable()->change();
            
            // Add foreign key back (with cascade delete)
            $table->foreign('file_entry_id')
                  ->references('id')
                  ->on('file_entries')
                  ->onDelete('cascade');
            
            // Add index for faster queries
            $table->index('file_entry_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('telegram_file_metadata', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['file_entry_id']);
            
            // Drop index
            $table->dropIndex(['file_entry_id']);
            
            // Make file_entry_id non-nullable and unique again
            $table->unsignedInteger('file_entry_id')->nullable(false)->unique()->change();
            
            // Add foreign key back
            $table->foreign('file_entry_id')
                  ->references('id')
                  ->on('file_entries')
                  ->onDelete('cascade');
        });
    }
}
