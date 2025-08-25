<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileEntry;
use App\Models\UserTelegramSettings;
use App\Services\Storage\TelegramStorageDriver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Common\Core\BaseController;
use Common\Files\Actions\CreateFileEntry;
use Common\Files\Actions\UploadFile;
use Common\Settings\Settings;

class FileApiController extends BaseController
{
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Upload file via API
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'parent_id' => 'nullable|integer|exists:file_entries,id',
            'telegram_chat_id' => 'nullable|string',
            'send_to_telegram' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        try {
            $user = Auth::user();
            $file = $request->file('file');
            
            // Upload file using existing BeDrive logic
            $uploadAction = app(UploadFile::class);
            $fileEntry = $uploadAction->execute([
                'file' => $file,
                'parentId' => $request->input('parent_id'),
                'userId' => $user->id,
            ]);

            // Handle Telegram upload if requested
            $telegramResult = null;
            if ($request->input('send_to_telegram', false)) {
                $telegramResult = $this->sendToTelegram($fileEntry, $request->input('telegram_chat_id'));
            }

            return $this->success([
                'file' => $fileEntry,
                'telegram' => $telegramResult,
            ]);

        } catch (\Exception $e) {
            Log::error('API file upload error: ' . $e->getMessage());
            return $this->error('File upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload file from URL
     */
    public function uploadFromUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'filename' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer|exists:file_entries,id',
            'telegram_chat_id' => 'nullable|string',
            'send_to_telegram' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        try {
            $user = Auth::user();
            $url = $request->input('url');
            $filename = $request->input('filename') ?: basename(parse_url($url, PHP_URL_PATH)) ?: 'downloaded_file';

            // Download file from URL
            $tempFile = $this->downloadFileFromUrl($url);
            
            if (!$tempFile) {
                return $this->error('Failed to download file from URL', 400);
            }

            // Create file entry
            $createAction = app(CreateFileEntry::class);
            $fileEntry = $createAction->execute([
                'name' => $filename,
                'file_size' => filesize($tempFile),
                'mime' => mime_content_type($tempFile),
                'parent_id' => $request->input('parent_id'),
                'user_id' => $user->id,
                'disk_prefix' => 'uploads',
                'path' => $tempFile,
            ]);

            // Handle Telegram upload if requested
            $telegramResult = null;
            if ($request->input('send_to_telegram', false)) {
                $telegramResult = $this->sendToTelegram($fileEntry, $request->input('telegram_chat_id'));
            }

            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            return $this->success([
                'file' => $fileEntry,
                'telegram' => $telegramResult,
            ]);

        } catch (\Exception $e) {
            Log::error('API URL upload error: ' . $e->getMessage());
            return $this->error('URL upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List user's files
     */
    public function list(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'parent_id' => 'nullable|integer|exists:file_entries,id',
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);
            
            $query = FileEntry::where('user_id', $user->id);
            
            if ($request->has('parent_id')) {
                $query->where('parent_id', $request->input('parent_id'));
            } else {
                $query->whereNull('parent_id');
            }

            $files = $query->orderBy('created_at', 'desc')
                          ->paginate($perPage);

            // Add download URLs
            $files->getCollection()->transform(function ($file) {
                $file->download_url = route('api.v1.files.download', $file->id);
                return $file;
            });

            return $this->success($files);

        } catch (\Exception $e) {
            Log::error('API file list error: ' . $e->getMessage());
            return $this->error('Failed to list files: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get file details
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $file = FileEntry::where('id', $id)
                            ->where('user_id', $user->id)
                            ->firstOrFail();

            $file->download_url = route('api.v1.files.download', $file->id);

            return $this->success($file);

        } catch (\Exception $e) {
            Log::error('API file show error: ' . $e->getMessage());
            return $this->error('File not found', 404);
        }
    }

    /**
     * Download file
     */
    public function download($id)
    {
        try {
            $user = Auth::user();
            $file = FileEntry::where('id', $id)
                            ->where('user_id', $user->id)
                            ->firstOrFail();

            $disk = Storage::disk($file->disk_prefix ?? 'uploads');
            
            if (!$disk->exists($file->path)) {
                return $this->error('File not found on storage', 404);
            }

            return $disk->download($file->path, $file->name);

        } catch (\Exception $e) {
            Log::error('API file download error: ' . $e->getMessage());
            return $this->error('Download failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete file
     */
    public function delete($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $file = FileEntry::where('id', $id)
                            ->where('user_id', $user->id)
                            ->firstOrFail();

            // Delete from storage
            $disk = Storage::disk($file->disk_prefix ?? 'uploads');
            if ($disk->exists($file->path)) {
                $disk->delete($file->path);
            }

            // If file exists in Telegram, delete it from there as well
            if ($file->telegramFile) {
                try {
                    $driver = new TelegramStorageDriver();
                    $driver->deleteFile(
                        $file->telegramFile->telegram_file_id,
                        $file->telegramFile->chat_id
                    );
                } catch (\Exception $e) {
                    // Log the error but don't block the main deletion process
                    Log::error("Could not delete file from Telegram, but continuing with local deletion. Error: " . $e->getMessage());
                }
            }

            // Delete from database
            $file->delete();

            return $this->success(['message' => 'File deleted successfully']);

        } catch (\Exception $e) {
            Log::error('API file delete error: ' . $e->getMessage());
            return $this->error('Delete failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Forward file to user's Telegram chat
     */
    public function forward($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $file = FileEntry::where('id', $id)
                            ->where('user_id', $user->id)
                            ->firstOrFail();

            $userSettings = UserTelegramSettings::where('user_id', $user->id)->first();
            $targetChatId = $userSettings->telegram_user_chat_id ?? null;

            if (!$targetChatId) {
                return $this->error('User has not configured their Telegram chat ID.', 400);
            }

            $telegramFileId = $file->telegramFile->telegram_file_id ?? null;

            if (!$telegramFileId) {
                return $this->error('This file does not have a Telegram message ID associated with it.', 400);
            }

            $driver = app(TelegramStorageDriver::class);
            $success = $driver->forwardFile($telegramFileId, $targetChatId);

            if ($success) {
                return $this->success(['message' => 'File forwarded successfully.']);
            } else {
                return $this->error('Failed to forward file to Telegram.', 500);
            }

        } catch (\Exception $e) {
            Log::error('API file forward error: ' . $e->getMessage());
            return $this->error('Forwarding failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send file to Telegram
     */
    protected function sendToTelegram(FileEntry $fileEntry): ?array
    {
        try {
            $driver = new TelegramStorageDriver();
            $user = Auth::user();

            // The main destination is always the one from admin settings
            $mainChatId = $this->settings->get('telegram.storage_telegram_chat_id', 'me');

            // Get file's full path for the caption
            $caption = $fileEntry->getHumanReadablePath();

            // Get local filesystem path for the file
            $disk = Storage::disk($fileEntry->disk_prefix ?? 'uploads');
            $filePath = $disk->path($fileEntry->path);

            $uploadOptions = [
                'to' => $mainChatId,
                'caption' => $caption,
            ];

            // Check if user has forwarding enabled
            $userTelegramSettings = UserTelegramSettings::where('user_id', $user->id)->first();
            if ($userTelegramSettings && $userTelegramSettings->telegram_auto_forward && $userTelegramSettings->telegram_user_chat_id) {
                $uploadOptions['forward'] = $userTelegramSettings->telegram_user_chat_id;
            }

            $result = $driver->uploadFile($filePath, $uploadOptions);

            // After successful upload, create a TelegramFile record
            if ($result['success'] && isset($result['file_id'])) {
                $fileEntry->telegramFile()->create([
                    'telegram_file_id' => $result['file_id'],
                    'chat_id' => $mainChatId,
                    'caption' => $caption,
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Telegram upload error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Transfer a file already in the system to Telegram.
     */
    public function transferToTelegram(Request $request, FileEntry $fileEntry): JsonResponse
    {
        $this->authorize('update', $fileEntry);

        if ($fileEntry->type === 'folder') {
            return $this->error('Folders cannot be transferred to Telegram.', 422);
        }

        if ($fileEntry->telegramFile) {
            return $this->error('This file has already been transferred to Telegram.', 422);
        }

        try {
            // The sendToTelegram method handles everything, including creating the TelegramFile record.
            $result = $this->sendToTelegram($fileEntry);

            if ($result['success']) {
                return $this->success([
                    'message' => 'File transferred to Telegram successfully.',
                    'file' => $fileEntry->load('telegramFile'), // reload with the new relationship
                ]);
            } else {
                return $this->error($result['message'] ?? 'Failed to transfer file to Telegram.', 500);
            }
        } catch (\Exception $e) {
            Log::error("API file transfer error: " . $e->getMessage());
            return $this->error('Transfer failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download file from URL
     */
    protected function downloadFileFromUrl($url): ?string
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'api_download_');
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'BeDrive File Downloader',
                ]
            ]);

            $data = file_get_contents($url, false, $context);
            
            if ($data === false) {
                return null;
            }

            file_put_contents($tempFile, $data);
            return $tempFile;

        } catch (\Exception $e) {
            Log::error('URL download error: ' . $e->getMessage());
            return null;
        }
    }
}

