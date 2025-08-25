<?php

namespace App\Http\Controllers;

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
use Common\Settings\Settings;

class UrlUploadController extends BaseController
{
    protected $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Upload file from URL
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'urls' => 'required|array|min:1|max:10',
            'urls.*' => 'required|url',
            'parent_id' => 'nullable|integer|exists:file_entries,id',
            'telegram_chat_id' => 'nullable|string',
            'send_to_telegram' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        try {
            $user = Auth::user();
            $urls = $request->input('urls');
            $results = [];
            $errors = [];

            foreach ($urls as $url) {
                try {
                    $result = $this->uploadSingleUrl($url, $request);
                    $results[] = $result;
                } catch (\Exception $e) {
                    $errors[] = [
                        'url' => $url,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $this->success([
                'uploaded' => $results,
                'errors' => $errors,
                'total_uploaded' => count($results),
                'total_errors' => count($errors),
            ]);

        } catch (\Exception $e) {
            Log::error('URL upload error: ' . $e->getMessage());
            return $this->error('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload single file from URL
     */
    protected function uploadSingleUrl($url, Request $request): array
    {
        $user = Auth::user();
        
        // Extract filename from URL
        $filename = $this->extractFilenameFromUrl($url);
        
        // Download file from URL
        $tempFile = $this->downloadFileFromUrl($url);
        
        if (!$tempFile) {
            throw new \Exception('Failed to download file from URL: ' . $url);
        }

        try {
            // Get file info
            $fileSize = filesize($tempFile);
            $mimeType = mime_content_type($tempFile) ?: 'application/octet-stream';

            // Create file entry
            $createAction = app(CreateFileEntry::class);
            $fileEntry = $createAction->execute([
                'name' => $filename,
                'file_size' => $fileSize,
                'mime' => $mimeType,
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

            return [
                'url' => $url,
                'file' => $fileEntry,
                'telegram' => $telegramResult,
            ];

        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Extract filename from URL
     */
    protected function extractFilenameFromUrl($url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename($path);
        
        if (empty($filename) || strpos($filename, '.') === false) {
            // Generate filename based on URL hash
            $filename = 'downloaded_' . substr(md5($url), 0, 8) . '.bin';
        }
        
        return $filename;
    }

    /**
     * Download file from URL
     */
    protected function downloadFileFromUrl($url): ?string
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'url_upload_');
            
            // Set up context with timeout and user agent
            $context = stream_context_create([
                'http' => [
                    'timeout' => 60,
                    'user_agent' => 'BeDrive File Downloader/1.0',
                    'follow_location' => true,
                    'max_redirects' => 5,
                ]
            ]);

            // Download file
            $data = file_get_contents($url, false, $context);
            
            if ($data === false) {
                return null;
            }

            // Check file size (limit to 100MB)
            if (strlen($data) > 100 * 1024 * 1024) {
                throw new \Exception('File too large (max 100MB)');
            }

            file_put_contents($tempFile, $data);
            return $tempFile;

        } catch (\Exception $e) {
            Log::error('URL download error: ' . $e->getMessage());
            throw new \Exception('Download failed: ' . $e->getMessage());
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
     * Get supported URL patterns
     */
    public function getSupportedPatterns(): JsonResponse
    {
        return $this->success([
            'patterns' => [
                'Direct file URLs (http://example.com/file.pdf)',
                'Google Drive share links',
                'Dropbox share links',
                'OneDrive share links',
                'GitHub raw file links',
                'Any direct download link',
            ],
            'max_file_size' => '100MB',
            'max_urls_per_request' => 10,
        ]);
    }
}

