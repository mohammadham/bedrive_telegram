<?php

namespace Common\Files\Tus;

use Common\Core\BaseController;
use Common\Files\Actions\CreateFileEntry;
use Common\Files\Actions\StoreFile;
use Common\Files\Events\FileUploaded;
use Common\Files\FileEntry;
use Common\Files\FileEntryPayload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
class TusFileEntryController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store()
    {
        $data = $this->validate(request(), [
            'uploadKey' => 'required|string',
        ]);

        Log::info('[TUS-ENTRY] Creating file entry', [
            'upload_key' => $data['uploadKey'],
        ]);

        $tusData = app(TusCache::class)->get($data['uploadKey']);

        if (!$tusData) {
            Log::error('[TUS-ENTRY] Upload session not found in cache', [
                'upload_key' => $data['uploadKey'],
            ]);
            return $this->error();
        }

        $metadata = $tusData['metadata'];
        $tusFilePath = $tusData['file_path'];
        $metadata['size'] = $tusData['size'];
        
        Log::info('[TUS-ENTRY] TUS file info', [
            'upload_key' => $data['uploadKey'],
            'file_path' => $tusFilePath,
            'file_exists' => file_exists($tusFilePath),
            'file_size' => file_exists($tusFilePath) ? filesize($tusFilePath) : 0,
            'metadata' => $metadata,
        ]);
        
        // tus temp file fingerprint, not needed anymore
        unset($metadata['name']);

        $payload = new FileEntryPayload($metadata);

        $this->authorize('store', [FileEntry::class, $payload->parentId]);

        Log::info('[TUS-ENTRY] Storing file with StoreFile action', [
            'upload_key' => $data['uploadKey'],
            'file_path' => $tusFilePath,
        ]);

        $storedPath = app(StoreFile::class)->execute($payload, [
            'path' => $tusFilePath,
            'moveFile' => true,
        ]);
        
        if (!$storedPath) {
            Log::error('[TUS-ENTRY] StoreFile returned false', [
                'upload_key' => $data['uploadKey'],
                'file_path' => $tusFilePath,
            ]);
        } else {
            Log::info('[TUS-ENTRY] File stored successfully', [
                'upload_key' => $data['uploadKey'],
                'stored_path' => $storedPath,
            ]);
        }

        $fileEntry = app(CreateFileEntry::class)->execute($payload);
        event(new FileUploaded($fileEntry));
        File::delete($tusFilePath);
        
        Log::info('[TUS-ENTRY] File entry created successfully', [
            'upload_key' => $data['uploadKey'],
            'file_entry_id' => $fileEntry->id,
            'file_name' => $fileEntry->name,
        ]);
        
        return $this->success(['fileEntry' => $fileEntry]);
    }
}
