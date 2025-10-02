<?php

namespace Common\Files\Telegram;

use App\Models\FileEntry;
use App\Models\TelegramFileMetadata;
use Common\Files\Telegram\Exceptions\TelegramException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Telegram Storage Service
 * 
 * High-level service for managing files with Telegram storage
 * Integrates FileEntry with TelegramAdapter
 */
class TelegramStorageService
{
    protected string $disk = 'telegram';

    public function __construct(?string $disk = null)
    {
        if ($disk) {
            $this->disk = $disk;
        }
    }

    /**
     * Upload a file and create FileEntry
     *
     * @param string $filePath Local file path
     * @param array $fileEntryData Data for FileEntry
     * @param array $options Upload options
     * @return array ['file_entry' => FileEntry, 'metadata' => TelegramFileMetadata]
     */
    public function uploadFile(
        string $filePath,
        array $fileEntryData,
        array $options = []
    ): array {
        try {
            // Validate file
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            $fileSize = filesize($filePath);
            $mimeType = mime_content_type($filePath);

            // Create FileEntry
            $fileEntry = FileEntry::create(array_merge([
                'name' => basename($filePath),
                'file_name' => basename($filePath),
                'mime' => $mimeType,
                'file_size' => $fileSize,
                'type' => 'file',
            ], $fileEntryData));

            // Upload to Telegram
            $uploadResult = Storage::disk($this->disk)
                ->getAdapter()
                ->manager
                ->uploadFile($filePath, null, array_merge([
                    'filename' => $fileEntry->file_name,
                    'caption' => $fileEntry->name,
                ], $options));

            // Create metadata
            $metadata = TelegramMetadataHelper::createMetadata($fileEntry, [
                'file_id' => $uploadResult['file_id'],
                'file_unique_id' => $uploadResult['file_unique_id'] ?? null,
                'message_id' => $uploadResult['message_id'],
                'channel_id' => $uploadResult['channel_id'],
                'upload_method' => $uploadResult['upload_method'],
                'upload_status' => 'completed',
                'uploaded_at' => now(),
            ]);

            Log::info('File uploaded to Telegram via StorageService', [
                'file_entry_id' => $fileEntry->id,
                'message_id' => $uploadResult['message_id'],
                'method' => $uploadResult['upload_method'],
            ]);

            return [
                'file_entry' => $fileEntry->fresh(),
                'metadata' => $metadata,
                'upload_result' => $uploadResult,
            ];
        } catch (TelegramException $e) {
            Log::error('Telegram upload failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Download a file by FileEntry
     *
     * @param FileEntry $fileEntry
     * @param string $savePath
     * @return bool
     */
    public function downloadFile(FileEntry $fileEntry, string $savePath): bool
    {
        $metadata = $fileEntry->telegramMetadata;

        if (!$metadata) {
            throw new \Exception('No Telegram metadata found for file');
        }

        // Determine file ID based on upload method
        $fileId = $metadata->isUploadedViaBot()
            ? $metadata->telegram_file_id
            : "{$metadata->channel_id}:{$metadata->message_id}";

        $manager = Storage::disk($this->disk)->getAdapter()->manager;

        $result = $manager->downloadFile(
            $fileId,
            $savePath,
            $metadata->upload_method
        );

        if ($result) {
            $metadata->touchLastAccessed();
        }

        return $result;
    }

    /**
     * Delete a file
     *
     * @param FileEntry $fileEntry
     * @return bool
     */
    public function deleteFile(FileEntry $fileEntry): bool
    {
        $metadata = $fileEntry->telegramMetadata;

        if (!$metadata) {
            Log::warning('No Telegram metadata found for file deletion', [
                'file_entry_id' => $fileEntry->id,
            ]);
            return false;
        }

        try {
            $manager = Storage::disk($this->disk)->getAdapter()->manager;

            $result = $manager->deleteFile(
                $metadata->channel_id,
                $metadata->message_id,
                $metadata->upload_method
            );

            if ($result) {
                $metadata->delete();
                $fileEntry->delete();
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to delete file', [
                'file_entry_id' => $fileEntry->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get file URL (returns a data URL with base64 content)
     *
     * @param FileEntry $fileEntry
     * @return string
     */
    public function getFileUrl(FileEntry $fileEntry): string
    {
        $metadata = $fileEntry->telegramMetadata;

        if (!$metadata) {
            throw new \Exception('No Telegram metadata found');
        }

        // For Telegram, we can't provide direct URLs
        // Return a route that will handle the download
        return route('telegram.file.download', [
            'id' => $fileEntry->id,
            'hash' => $fileEntry->hash,
        ]);
    }

    /**
     * Get file contents
     *
     * @param FileEntry $fileEntry
     * @return string
     */
    public function getFileContents(FileEntry $fileEntry): string
    {
        $tempPath = $this->getTempPath();

        try {
            $this->downloadFile($fileEntry, $tempPath);
            $contents = file_get_contents($tempPath);
            @unlink($tempPath);
            return $contents;
        } catch (\Exception $e) {
            @unlink($tempPath);
            throw $e;
        }
    }

    /**
     * Check if file exists in Telegram
     *
     * @param FileEntry $fileEntry
     * @return bool
     */
    public function fileExists(FileEntry $fileEntry): bool
    {
        $metadata = $fileEntry->telegramMetadata;
        return $metadata && $metadata->upload_status === 'completed';
    }

    /**
     * Get upload statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return TelegramMetadataHelper::getUploadStatistics();
    }

    /**
     * List all files in Telegram storage
     *
     * @param array $filters
     * @return \Illuminate\Support\Collection
     */
    public function listFiles(array $filters = [])
    {
        $query = TelegramFileMetadata::with('fileEntry')
            ->where('upload_status', 'completed');

        if (isset($filters['upload_method'])) {
            $query->where('upload_method', $filters['upload_method']);
        }

        if (isset($filters['from_date'])) {
            $query->where('uploaded_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('uploaded_at', '<=', $filters['to_date']);
        }

        return $query->get();
    }

    /**
     * Get temp file path
     */
    protected function getTempPath(): string
    {
        $tempDir = storage_path('app/telegram/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        return tempnam($tempDir, 'tg_download_');
    }

    /**
     * Set the disk to use
     *
     * @param string $disk
     * @return $this
     */
    public function disk(string $disk): self
    {
        $this->disk = $disk;
        return $this;
    }
}
