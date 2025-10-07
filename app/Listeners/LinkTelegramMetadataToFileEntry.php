<?php

namespace App\Listeners;

use App\Models\TelegramFileMetadata;
use Common\Files\Events\FileEntryCreated;
use Common\Files\Events\FileUploaded;
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
     * Check if Telegram driver is enabled
     */
    protected function isTelegramDriverEnabled(): bool
    {
        // Check if telegram is set as uploads or public disk driver
        $uploadsDriver = config('common.site.uploads_disk_driver');
        $publicDriver = config('common.site.public_disk_driver');
        
        return $uploadsDriver === 'telegram' || $publicDriver === 'telegram';
    }
    /**
     * Handle the event.
     *
     * @param FileEntryCreated $event
     * @return void
     */
    public function handle(FileEntryCreated $event): void
    {
        $fileEntry = $event->fileEntry;

        Log::info('Linked Telegram metadata to FileEntry', [
                'isTelegramDriverEnabled' => $this->isTelegramDriverEnabled(),
                'file_entry_id' => $fileEntry->type,
                
            ]);
        // Only for file types (not folders)
        if ($fileEntry->type === 'folder') {
            return;
        }
        // Check if the file is stored on Telegram disk
        
        if (! $this->isTelegramDriverEnabled()) {
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
            
            // 🔥 Dispatch FileUploaded event برای Telegram uploads
            // اولین FileUploaded از FileEntriesController هنوز metadata ندارد
            // این dispatch دوم است که metadata دارد و AutoForward می‌تواند کار کند
            // 
            // ✅ IMPORTANT: فقط یک بار dispatch کنیم!
            // چون این listener ممکن است چند بار فراخوانی شود (مثلاً از queue retry)
            // بررسی می‌کنیم که metadata تازه به این FileEntry link شده باشد
            if (!$metadata->wasChanged('file_entry_id')) {
                // metadata قبلاً به این FileEntry link شده بود، skip
                Log::debug('Skipping FileUploaded dispatch: metadata already linked', [
                    'file_entry_id' => $fileEntry->id,
                    'metadata_id' => $metadata->id,
                ]);
                return;
            }
            Log::debug('Dispatching FileUploaded with metadata for AutoForward', [
                'file_entry_id' => $fileEntry->id,
                'metadata_id' => $metadata->id,
            ]);
            
            // Refresh fileEntry to ensure metadata is loaded
            $fileEntry = $fileEntry->fresh(['owner', 'telegramMetadata']);
            
            event(new FileUploaded($fileEntry));
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
