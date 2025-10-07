<?php

namespace App\Http\Controllers;

use Common\Files\Telegram\TelegramUrlUploadService;
use Common\Files\Telegram\TelegramUrlUploadWithProgress;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Telegram URL Upload Controller
 * 
 * Handles uploading files from URLs directly to Telegram
 * Phase 8.2: با Progress Tracking
 */
class TelegramUrlUploadController extends BaseController
{
    protected TelegramUrlUploadService $uploadService;
    protected TelegramUrlUploadWithProgress $uploadWithProgress;

    public function __construct(
        TelegramUrlUploadService $uploadService,
        TelegramUrlUploadWithProgress $uploadWithProgress
    ) {
        $this->uploadService = $uploadService;
        $this->uploadWithProgress = $uploadWithProgress;
    }

    /**
     * Upload single file from URL
     *
     * POST /api/v1/telegram/upload-url
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadSingle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'name' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1024',
            'parent_id' => 'nullable|integer|exists:file_entries,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(),[], 422);
        }

        try {
            // استفاده از uploadWithProgress برای tracking
            $result = $this->uploadWithProgress->uploadFromUrl(
                $request->input('url'),
                [
                    'name' => $request->input('name'),
                    'user_id' => auth()->id(),
                    'owner_id' => auth()->id(),
                    'parent_id' => $request->input('parent_id'),
                ],
                [
                    'caption' => $request->input('caption', ''),
                ]
            );

            return $this->success([
                'message' => 'File uploaded successfully from URL',
                'session_id' => $result['session_id'], // Phase 8.2: session_id برای tracking
                'file_entry' => $result['file_entry'],
                'metadata' => $result['metadata'],
            ]);

        } catch (\Exception $e) {
            return $this->error(
                'Failed to upload file from URL: ' . $e->getMessage(),[],
                500
            );
        }
    }

    /**
     * Upload multiple files from URLs
     *
     * POST /api/v1/telegram/upload-bulk-urls
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadBulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'urls' => 'required|array|min:1|max:100',
            'urls.*' => 'required|url|max:2048',
            'caption' => 'nullable|string|max:1024',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(),[], 422);
        }

        try {
            $urls = $request->input('urls');
            
            $result = $this->uploadService->uploadBulkUrls(
                $urls,
                [
                    'user_id' => auth()->id(),
                    'owner_id' => auth()->id(),
                ],
                [
                    'caption' => $request->input('caption', ''),
                ]
            );

            return $this->success([
                'message' => "Processed {$result['total']} URLs",
                'total' => $result['total'],
                'successful' => $result['successful'],
                'failed' => $result['failed'],
                'results' => $result['results'],
            ]);

        } catch (\Exception $e) {
            return $this->error(
                'Failed to process bulk upload: ' . $e->getMessage(),[],
                500
            );
        }
    }

    /**
     * Validate URL before upload (optional check)
     *
     * POST /api/v1/telegram/validate-url
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
        ]);
        Log::info('Login user request received', [
            'info' => $request->all(),
            
        ]);
        
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(),[], 422);
        }

        $url = $request->input('url');

        try {
            // Check if URL is accessible
            $headers = @get_headers($url, 1);
            
            if (!$headers || !str_contains($headers[0], '200')) {
                return $this->error('URL is not accessible',[], 400);
            }

            // Get content type and size
            $contentType = $headers['Content-Type'] ?? 'unknown';
            $contentLength = $headers['Content-Length'] ?? 0;

            if (is_array($contentType)) {
                $contentType = end($contentType);
            }
            if (is_array($contentLength)) {
                $contentLength = end($contentLength);
            }

            // Check file size
            $canUpload = \Common\Files\Telegram\TelegramFileManager::canUpload((int) $contentLength);

            return $this->success([
                'valid' => true,
                'content_type' => $contentType,
                'content_length' => (int) $contentLength,
                'content_length_formatted' => $this->formatBytes((int) $contentLength),
                'can_upload' => $canUpload['can_upload'],
                'upload_method' => $canUpload['method'],
                'reason' => $canUpload['reason'],
            ]);

        } catch (\Exception $e) {
            return $this->error('Failed to validate URL: ' . $e->getMessage(),[], 500);
        }
    }

    /**
     * Format bytes to human readable
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}