<?php

namespace Common\Files\Adapters;

use App\Models\FileEntry;
use App\Models\TelegramFileMetadata;
use Common\Files\Telegram\Exceptions\TelegramException;
use Common\Files\Telegram\TelegramFileManager;
use Common\Files\Telegram\TelegramMetadataHelper;
use Common\Files\Telegram\TelegramPathMapper;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;

/**
 * Telegram Flysystem Adapter
 * 
 * Integrates Telegram storage with Laravel's filesystem
 * Supports files up to 2GB using hybrid Bot API and User Account
 */
class TelegramAdapter implements FilesystemAdapter
{
    public TelegramFileManager $manager;
    protected string $channelId;
    protected string $prefix = '';

    public function __construct(array $config = [])
    {
        // $this->channelId = $config['channel_id'] ?? config('services.telegram.channel_id');
        // Try to get channel_id from: config array, database settings, or env/config
        $this->channelId = $config['channel_id'] 
            ?? config('services.telegram.channel_id')
            ?? settings('storage_telegram_channel_id');
        $this->prefix = $config['prefix'] ?? '';

        $this->manager = new TelegramFileManager($this->channelId);

        Log::info('TelegramAdapter initialized', [
            'channel_id' => $this->channelId,
            'prefix' => $this->prefix,
        ]);
    }

    /**
     * Write a file
     *
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return void
     * @throws UnableToWriteFile
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            // Create temporary file
            $tempPath = $this->createTempFile($contents);

            // Upload to Telegram
            $result = $this->manager->uploadFile($tempPath, $this->channelId, [
                'filename' => basename($path),
                'caption' => $config->get('caption', ''),
            ]);

            // Store metadata mapping
            $this->storePathMapping($path, $result);

            // Clean up temp file
            @unlink($tempPath);

            Log::info('File written to Telegram', [
                'path' => $path,
                'method' => $result['upload_method'],
                'message_id' => $result['message_id'],
            ]);
        } catch (TelegramException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        } catch (\Exception $e) {
            Log::error('Failed to write file to Telegram', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Write a file using a stream
     *
     * @param string $path
     * @param resource $contents
     * @param Config $config
     * @return void
     * @throws UnableToWriteFile
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            // Read stream to string
            $data = stream_get_contents($contents);
            $this->write($path, $data, $config);
        } catch (\Exception $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Read a file
     *
     * @param string $path
     * @return string
     * @throws UnableToReadFile
     */
    public function read(string $path): string
    {
        try {
            $metadata = $this->getMetadataByPath($path);

            if (!$metadata) {
                throw new \Exception("File not found: {$path}");
            }

            // Create temp download path
            $tempPath = $this->getTempPath();

            // Download from Telegram
            $fileId = $metadata->isUploadedViaBot()
                ? $metadata->telegram_file_id
                : "{$metadata->channel_id}:{$metadata->message_id}";

            $this->manager->downloadFile(
                $fileId,
                $tempPath,
                $metadata->upload_method
            );

            // Read contents
            $contents = file_get_contents($tempPath);

            // Clean up
            @unlink($tempPath);

            // Update last accessed
            $metadata->touchLastAccessed();

            return $contents;
        } catch (\Exception $e) {
            Log::error('Failed to read file from Telegram', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Read a file as a stream
     *
     * @param string $path
     * @return resource
     * @throws UnableToReadFile
     */
    public function readStream(string $path)
    {
        try {
            $contents = $this->read($path);
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);
            return $stream;
        } catch (\Exception $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Delete a file
     *
     * @param string $path
     * @return void
     * @throws UnableToDeleteFile
     */
    public function delete(string $path): void
    {
        try {
            $metadata = $this->getMetadataByPath($path);

            if (!$metadata) {
                // File doesn't exist, consider it deleted
                return;
            }

            // Delete from Telegram
            $this->manager->deleteFile(
                $metadata->channel_id,
                $metadata->message_id,
                $metadata->upload_method
            );

            // Delete metadata
            $metadata->delete();

            Log::info('File deleted from Telegram', [
                'path' => $path,
                'message_id' => $metadata->message_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete file from Telegram', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * Delete a directory
     *
     * @param string $path
     * @return void
     */
    public function deleteDirectory(string $path): void
    {
        // Telegram doesn't have directories, so this is a no-op
        // In a real implementation, you might want to delete all files with this prefix
    }

    /**
     * Create a directory
     *
     * @param string $path
     * @param Config $config
     * @return void
     */
    public function createDirectory(string $path, Config $config): void
    {
        // Telegram doesn't have directories, so this is a no-op
    }

    /**
     * Set visibility
     *
     * @param string $path
     * @param string $visibility
     * @return void
     */
    public function setVisibility(string $path, string $visibility): void
    {
        // Telegram files in private channels are always private
        // This is a no-op
    }

    /**
     * Check if a file exists
     *
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        try {
            $metadata = $this->getMetadataByPath($path);
            return $metadata !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a directory exists
     *
     * @param string $path
     * @return bool
     */
    public function directoryExists(string $path): bool
    {
        // استفاده از PathMapper برای چک وجود virtual directory
        $path = TelegramPathMapper::normalizePath($path);
        return TelegramPathMapper::directoryExists($path);
    }

    /**
     * List contents of a directory
     *
     * @param string $path
     * @param bool $deep
     * @return iterable
     */
    public function listContents(string $path, bool $deep): iterable
    {
        // Normalize path
        $path = TelegramPathMapper::normalizePath($path);

        // Use PathMapper to list directory contents
        $files = TelegramPathMapper::listDirectory($path, $deep);

        foreach ($files as $metadata) {
            $filePath = data_get($metadata->metadata, 'path', '');
            
            if ($filePath) {
                yield new FileAttributes(
                    $filePath,
                    $metadata->original_file_size,
                    null,
                    $metadata->uploaded_at?->timestamp ?? $metadata->updated_at->timestamp,
                    $metadata->original_mime_type
                );
            }
        }
    }

    /**
     * Move a file
     *
     * @param string $source
     * @param string $destination
     * @param Config $config
     * @return void
     */
    public function move(string $source, string $destination, Config $config): void
    {
        // بهینه‌سازی: فقط path را تغییر می‌دهیم بدون کپی مجدد
        $source = TelegramPathMapper::normalizePath($source);
        $destination = TelegramPathMapper::normalizePath($destination);

        if (TelegramPathMapper::move($source, $destination)) {
            Log::info('File moved successfully', [
                'from' => $source,
                'to' => $destination,
            ]);
        } else {
            // اگر با PathMapper نشد، از copy+delete استفاده می‌کنیم
            $this->copy($source, $destination, $config);
            $this->delete($source);
        }
    }

    /**
     * Copy a file
     *
     * @param string $source
     * @param string $destination
     * @param Config $config
     * @return void
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $contents = $this->read($source);
        $this->write($destination, $contents, $config);
    }

    /**
     * Get file size
     *
     * @param string $path
     * @return FileAttributes
     * @throws UnableToRetrieveMetadata
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            $metadata = $this->getMetadataByPath($path);

            if (!$metadata) {
                throw new \Exception("File not found: {$path}");
            }

            return new FileAttributes(
                $path,
                $metadata->original_file_size
            );
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::fileSize($path, $e->getMessage(), $e);
        }
    }

    /**
     * Get mime type
     *
     * @param string $path
     * @return FileAttributes
     * @throws UnableToRetrieveMetadata
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $metadata = $this->getMetadataByPath($path);

            if (!$metadata) {
                throw new \Exception("File not found: {$path}");
            }

            return new FileAttributes(
                $path,
                null,
                null,
                null,
                $metadata->original_mime_type
            );
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    /**
     * Get last modified timestamp
     *
     * @param string $path
     * @return FileAttributes
     * @throws UnableToRetrieveMetadata
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            $metadata = $this->getMetadataByPath($path);

            if (!$metadata) {
                throw new \Exception("File not found: {$path}");
            }

            return new FileAttributes(
                $path,
                null,
                null,
                $metadata->uploaded_at?->timestamp
            );
        } catch (\Exception $e) {
            throw UnableToRetrieveMetadata::lastModified($path, $e->getMessage(), $e);
        }
    }

    /**
     * Get visibility
     *
     * @param string $path
     * @return FileAttributes
     */
    public function visibility(string $path): FileAttributes
    {
        // All files in private Telegram channels are private
        return new FileAttributes($path, null, 'private');
    }

    /**
     * Get public URL (not supported for private channels)
     *
     * @param string $path
     * @param Config $config
     * @return string
     */
    public function publicUrl(string $path, Config $config): string
    {
        // Telegram private channels don't have public URLs
        throw new \RuntimeException('Public URLs are not supported for Telegram storage');
    }

    /**
     * Get temporary URL (not supported)
     *
     * @param string $path
     * @param \DateTimeInterface $expiresAt
     * @param Config $config
     * @return string
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        // Not supported for Telegram
        throw new \RuntimeException('Temporary URLs are not supported for Telegram storage');
    }

    /**
     * Get checksum
     *
     * @param string $path
     * @param Config $config
     * @return string
     */
    public function checksum(string $path, Config $config): string
    {
        $contents = $this->read($path);
        $algo = $config->get('checksum_algo', 'md5');
        return hash($algo, $contents);
    }

    /**
     * Create a temporary file
     */
    protected function createTempFile(string $contents): string
    {
        $tempPath = $this->getTempPath();
        file_put_contents($tempPath, $contents);
        return $tempPath;
    }

    /**
     * Get temporary file path
     */
    protected function getTempPath(): string
    {
        $tempDir = storage_path('app/telegram/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        return tempnam($tempDir, 'telegram_');
    }

    /**
     * Store path to metadata mapping
     */
    protected function storePathMapping(string $path, array $uploadResult): void
    {
        // Normalize path
        $path = TelegramPathMapper::normalizePath($path);

        // Find or create metadata
        $metadata = TelegramFileMetadata::where('message_id', $uploadResult['message_id'])
            ->where('channel_id', $uploadResult['channel_id'])
            ->first();

        if (!$metadata) {
            // Create new metadata entry
            $metadata = TelegramFileMetadata::create([
                'file_entry_id' => 0, // Temporary, should be linked to FileEntry
                'telegram_file_id' => $uploadResult['file_id'],
                'telegram_file_unique_id' => $uploadResult['file_unique_id'] ?? null,
                'message_id' => $uploadResult['message_id'],
                'channel_id' => $uploadResult['channel_id'],
                'upload_method' => $uploadResult['upload_method'],
                'original_file_size' => $uploadResult['file_size'],
                'original_mime_type' => $uploadResult['mime_type'],
                'telegram_file_type' => TelegramMetadataHelper::determineTelegramFileType($uploadResult['mime_type']),
                'upload_status' => 'completed',
                'uploaded_at' => now(),
            ]);
        }

        // Store path mapping using PathMapper
        TelegramPathMapper::store($path, $metadata);

        Log::info('Path mapping stored', [
            'path' => $path,
            'message_id' => $uploadResult['message_id'],
        ]);
    }

    /**
     * Get metadata by path
     */
    public function getMetadataByPath(string $path): ?TelegramFileMetadata
    {
        // Normalize path
        $path = TelegramPathMapper::normalizePath($path);

        // Use PathMapper to resolve
        return TelegramPathMapper::resolve($path);
    }
}
