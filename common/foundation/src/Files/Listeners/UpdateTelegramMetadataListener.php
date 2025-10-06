<?php

namespace Common\Files\Listeners;

use App\Models\TelegramFileMetadata;
use Common\Files\Events\FileEntryCreated;
use Common\Files\Telegram\TelegramPathMapper;
use Illuminate\Support\Facades\Log;

/**
 * Update Telegram metadata after FileEntry is created
 * 
 * This listener links the FileEntry to its corresponding TelegramFileMetadata
 * which was created during the upload process.
 */
class UpdateTelegramMetadataListener
{
    /**
     * Handle the event
     *
     * @param FileEntryCreated $event
     * @return void
     */
    public function handle(FileEntryCreated $event)
    {
        $fileEntry = $event->fileEntry;
        
        // Only process if storage driver is telegram
        if (!$this->isTelegramStorage($fileEntry)) {
            return;
        }
        
        try {
            // Get full path for the file
            $path = $this->getFileFullPath($fileEntry);
            
            Log::info('UpdateTelegramMetadataListener: Processing FileEntry', [
                'file_entry_id' => $fileEntry->id,
                'file_name' => $fileEntry->file_name,
                'path' => $path,
            ]);
            
            // Find metadata by path
            $metadata = TelegramPathMapper::resolve($path);
            
            if (!$metadata) {
                Log::warning('UpdateTelegramMetadataListener: No metadata found for path', [
                    'path' => $path,
                    'file_entry_id' => $fileEntry->id,
                ]);
                return;
            }
            
            // Update metadata with file_entry_id
            $metadata->update([
                'file_entry_id' => $fileEntry->id,
            ]);
            
            Log::info('UpdateTelegramMetadataListener: Metadata updated successfully', [
                'file_entry_id' => $fileEntry->id,
                'metadata_id' => $metadata->id,
                'telegram_file_id' => $metadata->telegram_file_id,
            ]);
            
        } catch (\Exception $e) {
            Log::error('UpdateTelegramMetadataListener: Failed to update metadata', [
                'file_entry_id' => $fileEntry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Check if file uses Telegram storage
     *
     * @param mixed $fileEntry
     * @return bool
     */
    protected function isTelegramStorage($fileEntry): bool
    {
        // Check if the disk configuration indicates Telegram
        $diskType = config('common.site.uploads_disk_driver');
        
        if ($diskType === 'telegram') {
            return true;
        }
        
        // Also check public disk
        $publicDiskType = config('common.site.public_disk_driver');
        if ($publicDiskType === 'telegram') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get full storage path for file
     *
     * @param mixed $fileEntry
     * @return string
     */
    protected function getFileFullPath($fileEntry): string
    {
        // FileEntry path structure: {user_id}/{file_name}
        // or for workspace: {workspace_id}/{file_name}
        
        if ($fileEntry->path) {
            return $fileEntry->path . '/' . $fileEntry->file_name;
        }
        
        // Fallback: try to construct from owner
        if ($fileEntry->owner_id) {
            return $fileEntry->owner_id . '/' . $fileEntry->file_name;
        }
        
        // Last resort: just file_name
        return $fileEntry->file_name;
    }
}
