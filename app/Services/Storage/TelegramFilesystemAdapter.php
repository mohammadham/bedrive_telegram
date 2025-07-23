<?php

namespace App\Services\Storage;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCheckFileExistence;
use Illuminate\Support\Facades\Log;
use Exception;

class TelegramFilesystemAdapter implements FilesystemAdapter
{
    protected $driver;
    protected $chatId;
    protected $fileRegistry;

    public function __construct(TelegramStorageDriver $driver, $chatId = null)
    {
        $this->driver = $driver;
        $this->chatId = $chatId;
        $this->fileRegistry = storage_path('app/telegram_file_registry.json');

        // Initialize file registry if it doesn't exist
        if (!file_exists($this->fileRegistry)) {
            file_put_contents($this->fileRegistry, json_encode([]));
        }
    }

    public function fileExists(string $path): bool
    {
        try {
            $registry = $this->getFileRegistry();
            if (!isset($registry[$path])) {
                return false;
            }
            return $this->driver->fileExists($registry[$path]['file_id']);
        } catch (Exception $e) {
            Log::error('Error checking file existence in Telegram: ' . $e->getMessage());
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        // Telegram doesn't have traditional directories
        // We'll simulate this by checking if any files exist with this path prefix
        try {
            $registry = $this->getFileRegistry();
            foreach (array_keys($registry) as $filePath) {
                if (strpos($filePath, $path . '/') === 0) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            Log::error('Error checking directory existence in Telegram: ' . $e->getMessage());
            return false;
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            // Create a temporary file with the contents
            $tempFile = tempnam(sys_get_temp_dir(), 'telegram_upload_');
            file_put_contents($tempFile, $contents);

            // Upload to Telegram
            $result = $this->driver->uploadFile($tempFile, $path, $this->chatId);

            // Clean up temp file
            unlink($tempFile);

            if ($result['success']) {
                // Update file registry
                $this->updateFileRegistry($path, [
                    'file_id' => $result['file_id'],
                    'size' => strlen($contents),
                    'uploaded_at' => time(),
                    'mime_type' => $config->get('mimetype', 'application/octet-stream')
                ]);
            } else {
                throw new UnableToWriteFile('Failed to upload file to Telegram');
            }
        } catch (Exception $e) {
            Log::error('Error writing file to Telegram: ' . $e->getMessage());
            throw new UnableToWriteFile('Unable to write file to Telegram: ' . $e->getMessage());
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            // Convert stream to string and use write method
            $stringContents = stream_get_contents($contents);
            $this->write($path, $stringContents, $config);
        } catch (Exception $e) {
            Log::error('Error writing stream to Telegram: ' . $e->getMessage());
            throw new UnableToWriteFile('Unable to write stream to Telegram: ' . $e->getMessage());
        }
    }

    public function read(string $path): string
    {
        try {
            $registry = $this->getFileRegistry();

            if (!isset($registry[$path])) {
                throw new UnableToReadFile('File not found in Telegram registry: ' . $path);
            }

            $fileInfo = $registry[$path];
            $tempFile = tempnam(sys_get_temp_dir(), 'telegram_download_');

            if ($this->driver->downloadFile($fileInfo['file_id'], $tempFile)) {
                $contents = file_get_contents($tempFile);
                unlink($tempFile);
                return $contents;
            } else {
                throw new UnableToReadFile('Failed to download file from Telegram');
            }
        } catch (Exception $e) {
            Log::error('Error reading file from Telegram: ' . $e->getMessage());
            throw new UnableToReadFile('Unable to read file from Telegram: ' . $e->getMessage());
        }
    }

    public function readStream(string $path)
    {
        try {
            $contents = $this->read($path);
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);
            return $stream;
        } catch (Exception $e) {
            Log::error('Error reading stream from Telegram: ' . $e->getMessage());
            throw new UnableToReadFile('Unable to read stream from Telegram: ' . $e->getMessage());
        }
    }

    public function delete(string $path): void
    {
        try {
            $registry = $this->getFileRegistry();

            if (isset($registry[$path])) {
                $fileInfo = $registry[$path];
                if ($this->driver->deleteFile($fileInfo['file_id'])) {
                    // Remove from registry
                    unset($registry[$path]);
                    $this->saveFileRegistry($registry);
                } else {
                    throw new UnableToDeleteFile('Failed to delete file from Telegram');
                }
            }
        } catch (Exception $e) {
            Log::error('Error deleting file from Telegram: ' . $e->getMessage());
            throw new UnableToDeleteFile('Unable to delete file from Telegram: ' . $e->getMessage());
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $registry = $this->getFileRegistry();
            $deletedFiles = [];

            foreach (array_keys($registry) as $filePath) {
                if (strpos($filePath, $path . '/') === 0) {
                    $this->delete($filePath);
                    $deletedFiles[] = $filePath;
                }
            }

            if (empty($deletedFiles)) {
                throw new UnableToDeleteDirectory('Directory not found or empty: ' . $path);
            }
        } catch (Exception $e) {
            Log::error('Error deleting directory from Telegram: ' . $e->getMessage());
            throw new UnableToDeleteDirectory('Unable to delete directory from Telegram: ' . $e->getMessage());
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Telegram doesn't have directories, so this is a no-op
        // We'll just log that a directory was "created"
        Log::info('Directory created in Telegram (virtual): ' . $path);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Telegram files don't have traditional visibility settings
        // This is a no-op
        Log::info('Visibility set for Telegram file (no-op): ' . $path . ' -> ' . $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        // All Telegram files are considered private by default
        return new FileAttributes($path, null, 'private');
    }

    public function mimeType(string $path): FileAttributes
    {
        try {
            $registry = $this->getFileRegistry();

            if (isset($registry[$path])) {
                $mimeType = $registry[$path]['mime_type'] ?? 'application/octet-stream';
                return new FileAttributes($path, null, null, null, $mimeType);
            }

            throw new UnableToCheckFileExistence('File not found in registry: ' . $path);
        } catch (Exception $e) {
            Log::error('Error getting mime type from Telegram: ' . $e->getMessage());
            return new FileAttributes($path, null, null, null, 'application/octet-stream');
        }
    }

    public function lastModified(string $path): FileAttributes
    {
        try {
            $registry = $this->getFileRegistry();

            if (isset($registry[$path])) {
                $timestamp = $registry[$path]['uploaded_at'] ?? time();
                return new FileAttributes($path, null, null, $timestamp);
            }

            throw new UnableToCheckFileExistence('File not found in registry: ' . $path);
        } catch (Exception $e) {
            Log::error('Error getting last modified from Telegram: ' . $e->getMessage());
            return new FileAttributes($path, null, null, time());
        }
    }

    public function fileSize(string $path): FileAttributes
    {
        try {
            $registry = $this->getFileRegistry();

            if (isset($registry[$path])) {
                $size = $registry[$path]['size'] ?? 0;
                return new FileAttributes($path, $size);
            }

            throw new UnableToCheckFileExistence('File not found in registry: ' . $path);
        } catch (Exception $e) {
            Log::error('Error getting file size from Telegram: ' . $e->getMessage());
            return new FileAttributes($path, 0);
        }
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $registry = $this->getFileRegistry();
            $contents = [];

            foreach ($registry as $filePath => $fileInfo) {
                if ($path === '' || strpos($filePath, $path . '/') === 0) {
                    $relativePath = $path === '' ? $filePath : substr($filePath, strlen($path) + 1);

                    // Skip if this is a nested file and we're not doing deep listing
                    if (!$deep && strpos($relativePath, '/') !== false) {
                        continue;
                    }

                    $contents[] = new FileAttributes(
                        $filePath,
                        $fileInfo['size'] ?? 0,
                        null,
                        $fileInfo['uploaded_at'] ?? time(),
                        $fileInfo['mime_type'] ?? 'application/octet-stream'
                    );
                }
            }

            return $contents;
        } catch (Exception $e) {
            Log::error('Error listing contents from Telegram: ' . $e->getMessage());
            return [];
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $registry = $this->getFileRegistry();

            if (isset($registry[$source])) {
                $registry[$destination] = $registry[$source];
                unset($registry[$source]);
                $this->saveFileRegistry($registry);
            } else {
                throw new UnableToMoveFile('Source file not found: ' . $source);
            }
        } catch (Exception $e) {
            Log::error('Error moving file in Telegram: ' . $e->getMessage());
            throw new UnableToMoveFile('Unable to move file in Telegram: ' . $e->getMessage());
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $registry = $this->getFileRegistry();

            if (isset($registry[$source])) {
                $registry[$destination] = $registry[$source];
                $this->saveFileRegistry($registry);
            } else {
                throw new UnableToCopyFile('Source file not found: ' . $source);
            }
        } catch (Exception $e) {
            Log::error('Error copying file in Telegram: ' . $e->getMessage());
            throw new UnableToCopyFile('Unable to copy file in Telegram: ' . $e->getMessage());
        }
    }

    protected function getFileRegistry(): array
    {
        try {
            $contents = file_get_contents($this->fileRegistry);
            return json_decode($contents, true) ?: [];
        } catch (Exception $e) {
            Log::error('Error reading file registry: ' . $e->getMessage());
            return [];
        }
    }

    protected function saveFileRegistry(array $registry): void
    {
        try {
            file_put_contents($this->fileRegistry, json_encode($registry, JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            Log::error('Error saving file registry: ' . $e->getMessage());
        }
    }

    protected function updateFileRegistry(string $path, array $fileInfo): void
    {
        $registry = $this->getFileRegistry();
        $registry[$path] = $fileInfo;
        $this->saveFileRegistry($registry);
    }
}

