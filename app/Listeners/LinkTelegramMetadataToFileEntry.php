<?php

namespace App\Listeners;

use App\Models\TelegramFileMetadata;
use Common\Files\Events\FileEntryCreated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Link Telegram metadata to newly created FileEntry
 * 
 * When a file is uploaded to Telegram storage, the metadata is created
 * before the FileEntry. This listener links them together after the
 * FileEntry is created.
 */
class LinkTelegramMetadataToFileEntry
{
    /**
     * Handle the event.
     *
     * @param FileEntryCreated $event
     * @return void
     */
    public function handle(FileEntryCreated $event): void
    {
        $fileEntry = $event->fileEntry;

        // Only for file types (not folders)
        if ($fileEntry->type !== 'file') {
            return;
        }

        // Check if the file is stored on Telegram disk
        $uploadsDisk = config('common.site.uploads_disk_driver');
        if ($uploadsDisk !== 'telegram') {
            return;
        }

        // Try to find unlinked metadata for this file
        // We search by original_file_size and recent upload time
        // Note: file_entry_id is now nullable (not 0) for unlinked metadata
        $metadata = TelegramFileMetadata::whereNull('file_entry_id')
            ->where('original_file_size', $fileEntry->file_size)
            ->where('upload_status', 'completed')
            ->whereBetween('uploaded_at', [
                now()->subMinutes(5), // Created within last 5 minutes
                now()->addMinute() // Allow for clock skew
            ])
            ->orderBy('uploaded_at', 'desc')
            ->first();

        if ($metadata) {
            // Link metadata to FileEntry
            $metadata->file_entry_id = $fileEntry->id;
            $metadata->save();

            Log::info('Linked Telegram metadata to FileEntry', [
                'file_entry_id' => $fileEntry->id,
                'metadata_id' => $metadata->id,
                'message_id' => $metadata->message_id,
                'telegram_file_id' => $metadata->telegram_file_id,
                'file_name' => $fileEntry->file_name,
            ]);
        } else {
            Log::warning('No Telegram metadata found for FileEntry', [
                'file_entry_id' => $fileEntry->id,
                'file_name' => $fileEntry->file_name,
                'file_size' => $fileEntry->file_size,
                'note' => 'Metadata should be created within 5 minutes of FileEntry',
            ]);
        }
    }
}
