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

use App\Models\TelegramFile;
use App\Models\FileEntry;

class TelegramFilesystemAdapter implements FilesystemAdapter
{
    protected $driver;
    protected $chatId;

    public function __construct(TelegramStorageDriver $driver, $chatId = null)
    {
        $this->driver = $driver;
        $this->chatId = $chatId;
    }

    public function fileExists(string $path): bool
    {
        try {
            $fileEntry = FileEntry::where('path', $path)->first();
            if (!$fileEntry || !$fileEntry->telegramFile) {
                return false;
            }
            return $this->driver->fileExists($fileEntry->telegramFile->telegram_file_id, $fileEntry->telegramFile->telegram_chat_id);
        } catch (Exception $e) {
            Log::error('Error checking file existence in Telegram: ' . $e->getMessage());
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        // A directory exists if there's at least one file entry whose path
        // starts with the directory path.
        return FileEntry::where('path', 'like', "$path/%")->exists();
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            // Create a temporary file with the contents
            $tempFile = tempnam(sys_get_temp_dir(), 'telegram_upload_');
            file_put_contents($tempFile, $contents);

            // Upload to Telegram
            $forwardToChatId = $config->get('forward_to_chat_id');
                   $uploadOptions = [
                'to' => $this->chatId,
                'caption' => $path,
                'forward' => $forwardToChatId,
                // سایر گزینه‌ها را می‌توانید بر اساس نیاز اضافه کنید
            ];
            $result = $this->driver->uploadFile($tempFile, $uploadOptions);

            // Clean up temp file
            unlink($tempFile);

            if ($result['success']) {
                $fileEntry = FileEntry::where('path', $path)->first();
                if ($fileEntry) {
                    TelegramFile::create([
                        'file_entry_id' => $fileEntry->id,
                        'telegram_file_id' => $result['file_id'],
                        'telegram_chat_id' => $this->chatId ?: 'me',
                    ]);
                }
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
            // Create a temporary file and write the stream to it
            $tempFile = tempnam(sys_get_temp_dir(), 'telegram_upload_stream_');
            $tempHandle = fopen($tempFile, 'w');
            stream_copy_to_stream($contents, $tempHandle);
            fclose($tempHandle);

            // Upload to Telegram
            $forwardToChatId = $config->get('forward_to_chat_id');
                   $uploadOptions = [
                'to' => $this->chatId,
                'caption' => $path,
                'forward' => $forwardToChatId,
                // سایر گزینه‌ها را می‌توانید بر اساس نیاز اضافه کنید
            ];
            $result = $this->driver->uploadFile($tempFile, $uploadOptions);

            // Get file size
            $size = filesize($tempFile);

            // Clean up temp file
            unlink($tempFile);

            if ($result['success']) {
                $fileEntry = FileEntry::where('path', $path)->first();
                if ($fileEntry) {
                    TelegramFile::create([
                        'file_entry_id' => $fileEntry->id,
                        'telegram_file_id' => $result['file_id'],
                        'telegram_chat_id' => $this->chatId ?: 'me',
                    ]);
                }
            } else {
                throw new UnableToWriteFile('Failed to upload stream to Telegram');
            }
        } catch (Exception $e) {
            Log::error('Error writing stream to Telegram: ' . $e->getMessage());
            throw new UnableToWriteFile('Unable to write stream to Telegram: ' . $e->getMessage());
        }
    }

    public function read(string $path): string
    {
        try {
            $fileEntry = FileEntry::where('path', $path)->first();
            if (!$fileEntry || !$fileEntry->telegramFile) {
                throw new UnableToReadFile('File not found in Telegram registry: ' . $path);
            }

            $telegramFile = $fileEntry->telegramFile;
            $tempDir = sys_get_temp_dir();
            $downloadOptions = [
                'from' => $telegramFile->telegram_chat_id ?: 'me',
                // سایر گزینه‌ها را می‌توانید بر اساس نیاز اضافه کنید
            ];
            $downloadedFiles = $this->driver->downloadFile($tempDir, $downloadOptions);

            // پیدا کردن فایل مناسب (در حالت عادی فقط یک فایل دانلود می‌شود)
            $foundFile = null;
            foreach ($downloadedFiles as $file) {
                if (basename($file) === $fileEntry->name || basename($file) === $fileEntry->path) {
                    $foundFile = $file;
                    break;
                }
            }
            if (!$foundFile && count($downloadedFiles) > 0) {
                $foundFile = $downloadedFiles[0];
            }
            if ($foundFile && file_exists($foundFile)) {
                $contents = file_get_contents($foundFile);
                unlink($foundFile);
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
            $fileEntry = FileEntry::where('path', $path)->first();
            if ($fileEntry && $fileEntry->telegramFile) {
                $telegramFile = $fileEntry->telegramFile;
                if ($this->driver->deleteFile($telegramFile->telegram_file_id, $telegramFile->telegram_chat_id)) {
                    $telegramFile->delete();
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
            $entries = FileEntry::where('path', 'like', "$path/%")->get();

            if ($entries->isEmpty()) {
                throw new UnableToDeleteDirectory('Directory not found or empty: ' . $path);
            }

            foreach ($entries as $entry) {
                $this->delete($entry->path);
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
        $fileEntry = FileEntry::where('path', $path)->first();
        if (!$fileEntry) {
            throw new UnableToCheckFileExistence('File not found: ' . $path);
        }
        return new FileAttributes($path, null, null, null, $fileEntry->mime);
    }

    public function lastModified(string $path): FileAttributes
    {
        $fileEntry = FileEntry::where('path', $path)->first();
        if (!$fileEntry) {
            throw new UnableToCheckFileExistence('File not found: ' . $path);
        }
        return new FileAttributes($path, null, null, $fileEntry->updated_at->getTimestamp());
    }

    public function fileSize(string $path): FileAttributes
    {
        $fileEntry = FileEntry::where('path', $path)->first();
        if (!$fileEntry) {
            throw new UnableToCheckFileExistence('File not found: ' . $path);
        }
        return new FileAttributes($path, $fileEntry->file_size);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $query = FileEntry::where('parent_id', function ($query) use ($path) {
            $query->select('id')
                ->from('file_entries')
                ->where('path', $path)
                ->limit(1);
        });

        if ($deep) {
            // This is a simplification. A true deep list would require a recursive query.
            $query->orWhere('path', 'like', "$path/%");
        }

        return $query->get()->map(function (FileEntry $entry) {
            return new FileAttributes(
                $entry->path,
                $entry->file_size,
                null,
                $entry->updated_at->getTimestamp(),
                $entry->mime
            );
        });
    }

    public function move(string $source, string $destination, Config $config): void
    {
        // The "move" operation is handled by changing the path in the `file_entries` table.
        // The link to the `telegram_files` table is via `file_entry_id`, so no changes
        // are needed here. This adapter does not need to be aware of path changes.
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $sourceEntry = FileEntry::where('path', $source)->first();
            $destinationEntry = FileEntry::where('path', 'like', "$destination%")->first();

            if ($sourceEntry && $sourceEntry->telegramFile && $destinationEntry) {
                 TelegramFile::create([
                    'file_entry_id' => $destinationEntry->id,
                    'telegram_file_id' => $sourceEntry->telegramFile->telegram_file_id,
                    'telegram_chat_id' => $sourceEntry->telegramFile->telegram_chat_id,
                ]);
            } else {
                throw new UnableToCopyFile('Source file not found in Telegram registry.');
            }
        } catch (Exception $e) {
            Log::error('Error copying file in Telegram: ' . $e->getMessage());
            throw new UnableToCopyFile('Unable to copy file in Telegram: ' . $e->getMessage());
        }
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        $fileEntry = FileEntry::where('path', $path)->firstOrFail();
        
        return app('url')->temporarySignedRoute(
            'telegram.download.start',
            $expiration,
            ['fileEntry' => $fileEntry->id]
        );
    }
}

 