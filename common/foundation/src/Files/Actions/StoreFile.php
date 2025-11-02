<?php

namespace Common\Files\Actions;

use Common\Files\FileEntryPayload;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\Mime\MimeTypes;
use Illuminate\Support\Facades\Log;
class StoreFile
{
    protected Filesystem $disk;
    protected array $diskOptions;
    protected FileEntryPayload $payload;

    public function execute(
        FileEntryPayload $payload,
        array $fileOptions,
    ): string|false {
        Log::info('[STORE-FILE] Starting file storage', [
            'filename' => $payload->filename,
            'mime' => $payload->clientMime,
            'size' => $payload->size,
            'public' => $payload->public,
            'disk_prefix' => $payload->diskPrefix,
            'file_options_keys' => array_keys($fileOptions),
        ]);
        
        $this->disk = $payload->public
            ? Storage::disk('public')
            : Storage::disk('uploads');

        $this->diskOptions = [
            'mimetype' => $payload->clientMime,
            'visibility' => $payload->visibility,
        ];

        $this->payload = $payload;

        if (
            // prevent uploading .htaccess files
            $payload->filename === '.htaccess' ||
            // dont store php files in public disk
            ($payload->public && $this->isPhpFile($payload, $fileOptions)) ||
            // prevent path traversal or storing at root in user specified folder
            ($payload->diskPrefix &&
                (Str::contains($payload->diskPrefix, '..') ||
                    $payload->diskPrefix === '/'))
        ) {
            Log::error('[STORE-FILE] File blocked for security reasons', [
                'filename' => $payload->filename,
            ]);
            abort(403);
        }

        $result = false;
        if (isset($fileOptions['file'])) {
            Log::info('[STORE-FILE] Using uploaded file method');
            $result = $this->storeUploadedFile($fileOptions['file']);
        } elseif (isset($fileOptions['contents'])) {
            Log::info('[STORE-FILE] Using string contents method');
            $result = $this->storeStringContents($fileOptions['contents']);
        } elseif (isset($fileOptions['path'])) {
            Log::info('[STORE-FILE] Using file path method', [
                'source_path' => $fileOptions['path'],
                'file_exists' => file_exists($fileOptions['path']),
                'file_size' => file_exists($fileOptions['path']) ? filesize($fileOptions['path']) : 0,
                'move_file' => Arr::get($fileOptions, 'moveFile', false),
            ]);
            
            // if source and destination is local (and not temp dir) move file
            // instead of copying or using streams, this will be a lot faster
            if (
                Arr::get($fileOptions, 'moveFile') === true &&
                $this->disk->getAdapter() instanceof LocalFilesystemAdapter
            ) {
                Log::info('[STORE-FILE] Using local file move');
                $result = $this->storeLocalFile($fileOptions['path']);
            } else {
                Log::info('[STORE-FILE] Using file upload');
                $result = $this->storeUploadedFile(new File($fileOptions['path']));
            }
        }

        if ($result) {
            Log::info('[STORE-FILE] File stored successfully', [
                'stored_path' => $result,
            ]);
        } else {
            Log::error('[STORE-FILE] File storage failed', [
                'filename' => $payload->filename,
            ]);
        }

        return $result;
    }

    protected function storeUploadedFile(File|UploadedFile $file): string|false
    {
        return $this->disk->putFileAs(
            $this->payload->diskPrefix,
            $file,
            $this->payload->filename,
            $this->diskOptions,
        );
    }

    protected function storeStringContents(string $contents): string|false
    {
        return $this->disk->put(
            "{$this->payload->diskPrefix}/{$this->payload->filename}",
            $contents,
            $this->diskOptions,
        );
    }

    protected function storeLocalFile(string $sourcePath): string|false
    {
        $dirPath = $this->disk->path($this->payload->diskPrefix);

        FileFacade::ensureDirectoryExists($dirPath);
        $stored = @rename($sourcePath, "$dirPath/{$this->payload->filename}");

        if ($stored) {
            return "{$this->payload->diskPrefix}/{$this->payload->filename}";
        }

        return false;
    }

    protected function isPhpFile(
        FileEntryPayload $payload,
        array $fileOptions,
    ): bool {
        if (
            Str::of($payload->clientExtension)
                ->lower()
                ->startsWith(['php', 'phtml'])
        ) {
            return true;
        }

        $mimeType = null;
        if (isset($fileOptions['file'])) {
            $mimeType = $fileOptions['file']->getMimeType();
        } elseif (isset($fileOptions['path'])) {
            $mimeType = MimeTypes::getDefault()->guessMimeType(
                $fileOptions['path'],
            );
        }

        return $mimeType === 'application/x-php';
    }
}
