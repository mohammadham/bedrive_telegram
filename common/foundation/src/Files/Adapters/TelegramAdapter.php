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
        // Try to get channel_id from: config array, database settings, or env/config
        $this->channelId = $config['channel_id'] 
            ?? config('services.telegram.channel_id')
            ?? settings('storage_telegram_channel_id');
        $this->prefix = $config['prefix'] ?? '';

        // Pass full config to TelegramFileManager
        $this->manager = new TelegramFileManager($this->channelId, $config);

        Log::info('TelegramAdapter initialized', [
            'channel_id' => $this->channelId,
            'prefix' => $this->prefix,
            'config_keys' => array_keys($config),
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
            Log::info('TelegramAdapter writing file', [
                'path' => $path,
                'size' => strlen($contents),
            ]);
            
            // Create temporary file
            $tempPath = $this->createTempFile($contents);
            
            Log::info('Temporary file created', [
                'temp_path' => $tempPath,
                'exists' => file_exists($tempPath),
            ]);

            // Upload to Telegram
            Log::info('Starting Telegram upload', [
                'channel_id' => $this->channelId,
                'filename' => basename($path),
            ]);
            
            $result = $this->manager->uploadFile($tempPath, $this->channelId, [
                'filename' => basename($path),
                'caption' => $config->get('caption', ''),
            ]);
            
            Log::info('Upload result received', [
                'result_keys' => array_keys($result),
                'file_id' => $result['file_id'] ?? 'MISSING',
                'message_id' => $result['message_id'] ?? 'MISSING',
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
            Log::error('TelegramException during write', [
                'path' => $path,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        } catch (\Exception $e) {
            Log::error('Failed to write file to Telegram', [
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
                throw UnableToReadFile::fromLocation($path, "File not found in Telegram storage");
            }

            // Create temp download path
            $tempPath = $this->getTempPath();

            // IMPORTANT: Bot API file_id often becomes invalid
            // PRIMARY: Try User Account if available (more reliable)
            // FALLBACK: Try Bot with file_id
            
            $downloaded = false;
            $lastError = null;

            // Strategy 1: If User Account is configured, use it (most reliable)
            try {
                // Check if user credentials are configured
                $hasUserConfig = !empty($this->manager->getConfig()['api_id']) 
                    && !empty($this->manager->getConfig()['api_hash']);

                if ($hasUserConfig) {
                    Log::info('Attempting download via User Account (primary)', [
                        'message_id' => $metadata->message_id,
                        'channel_id' => $metadata->channel_id,
                    ]);

                    // Use User Account with message_id
                    $fileId = "{$metadata->channel_id}:{$metadata->message_id}";
                    $this->manager->downloadFile($fileId, $tempPath, 'user');
                    $downloaded = true;
                    
                    Log::info('Downloaded successfully via User Account');
                }
            } catch (\Exception $e) {
                $lastError = $e;
                Log::warning('User Account download failed, will try Bot', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Strategy 2: Try Bot API with file_id (if not downloaded yet)
            if (!$downloaded && $metadata->isUploadedViaBot()) {
                try {
                    Log::info('Attempting download via Bot API (fallback)', [
                        'file_id' => $metadata->telegram_file_id,
                    ]);

                    $fallbackData = [
                        'message_id' => $metadata->message_id,
                        'channel_id' => $metadata->channel_id,
                    ];

                    $this->manager->downloadFile(
                        $metadata->telegram_file_id,
                        $tempPath,
                        'bot',
                        $fallbackData
                    );
                    $downloaded = true;
                    
                    Log::info('Downloaded successfully via Bot API');
                } catch (\Exception $e) {
                    $lastError = $e;
                    Log::error('Bot API download also failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // If still not downloaded, fail
            if (!$downloaded) {
                $errorMsg = 'Failed to download file using any method. ';
                if ($lastError) {
                    $errorMsg .= 'Last error: ' . $lastError->getMessage();
                }
                $errorMsg .= ' Configure User Account credentials for reliable downloads.';
                
                throw new \Exception($errorMsg);
            }

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
                throw UnableToRetrieveMetadata::fileSize($path, "File not found in Telegram storage");
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
                throw UnableToRetrieveMetadata::mimeType($path, "File not found in Telegram storage");
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
                throw UnableToRetrieveMetadata::lastModified($path, "File not found in Telegram storage");
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
        // DON'T normalize path here - keep original format ({uuid}/{uuid}.ext)
        // Normalization can cause issues with UUID-based paths
        Log::info('Storing path mapping', [
            'original_path' => $path,
            'message_id' => $uploadResult['message_id'],
        ]);

        // Find or create metadata
        $metadata = TelegramFileMetadata::where('message_id', $uploadResult['message_id'])
            ->where('channel_id', $uploadResult['channel_id'])
            ->first();

        if (!$metadata) {
            // Try to find FileEntry by path (UUID-based path)
            // Path format: {uuid}/{uuid}.{extension}
            // Extract UUID from path
            $pathParts = explode('/', $path);
            $fileEntry = null;
            
            if (count($pathParts) >= 2) {
                // Try to find FileEntry by file_name (which contains UUID)
                $fileName = $pathParts[count($pathParts) - 1];
                $fileEntry = FileEntry::where('file_name', $fileName)
                    ->orWhere('file_name', 'LIKE', '%' . basename($path))
                    ->latest()
                    ->first();
            }
            
            // Create new metadata entry
            // Note: file_entry_id is null here because FileEntry is created AFTER storage
            // It will be linked later via UpdateTelegramMetadataListener
            $metadata = TelegramFileMetadata::create([
                'file_entry_id' => null, // Will be updated by event listener
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

        // Store path mapping using PathMapper (it will handle normalization internally)
        TelegramPathMapper::store($path, $metadata);

        Log::info('Path mapping stored', [
            'path' => $path,
            'message_id' => $uploadResult['message_id'],
            'file_entry_id' => $metadata->file_entry_id,
            'metadata_id' => $metadata->id,
        ]);
    }

    /**
     * Get metadata by path
     */
    public function getMetadataByPath(string $path): ?TelegramFileMetadata
    {
        // DON'T normalize - use exact path
        Log::info('Getting metadata by path', ['path' => $path]);
        
        // Use PathMapper to resolve (it uses the exact stored path)
        $metadata = TelegramPathMapper::resolve($path);
        
        if (!$metadata) {
            Log::warning('No metadata found for path', ['path' => $path]);
        }
        
        return $metadata;
    }
}
