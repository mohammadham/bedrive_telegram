<?php

namespace Common\Files\Adapters;

use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use League\Flysystem\Filesystem;

/**
 * Laravel Filesystem Adapter wrapper for Telegram
 * 
 * This wraps the TelegramAdapter for Laravel's filesystem
 */
class TelegramFilesystemAdapter extends LaravelFilesystemAdapter
{
    /**
     * Create a new Telegram filesystem adapter
     *
     * @param Filesystem $driver
     * @param TelegramAdapter $adapter
     * @param array $config
     */
    public function __construct(Filesystem $driver, TelegramAdapter $adapter, array $config = [])
    {
        parent::__construct($driver, $adapter, $config);
    }

    /**
     * Get the Telegram file manager
     *
     * @return \Common\Files\Telegram\TelegramFileManager
     */
    public function getTelegramManager()
    {
        return $this->adapter->manager ?? null;
    }

    /**
     * Upload a file directly to Telegram
     *
     * @param string $path
     * @param string $filePath
     * @param array $options
     * @return array
     */
    public function uploadToTelegram(string $path, string $filePath, array $options = []): array
    {
        $adapter = $this->adapter;
        if ($adapter instanceof TelegramAdapter) {
            return $adapter->manager->uploadFile($filePath, null, array_merge([
                'filename' => basename($path),
            ], $options));
        }

        throw new \RuntimeException('Adapter is not a TelegramAdapter');
    }

    /**
     * Download a file from Telegram
     *
     * @param string $path
     * @param string $savePath
     * @return bool
     */
    public function downloadFromTelegram(string $path, string $savePath): bool
    {
        $contents = $this->get($path);
        return file_put_contents($savePath, $contents) !== false;
    }

    /**
     * Get Telegram metadata for a file
     *
     * @param string $path
     * @return \App\Models\TelegramFileMetadata|null
     */
    public function getTelegramMetadata(string $path)
    {
        $adapter = $this->adapter;
        if ($adapter instanceof TelegramAdapter) {
            return $adapter->getMetadataByPath($path);
        }

        return null;
    }

    /**
     * Check which upload method would be used for a file size
     *
     * @param int $fileSize
     * @return string 'bot' or 'user'
     */
    public function determineUploadMethod(int $fileSize): string
    {
        return \Common\Files\Telegram\TelegramFileManager::determineMethod($fileSize);
    }

    /**
     * Check if a file size can be uploaded
     *
     * @param int $fileSize
     * @return array
     */
    public function canUpload(int $fileSize): array
    {
        return \Common\Files\Telegram\TelegramFileManager::canUpload($fileSize);
    }
}
