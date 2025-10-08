<?php

namespace App\Http\Controllers;

use Common\Files\Telegram\TelegramUrlUploadService;
use Common\Files\Telegram\TelegramUrlUploadWithProgress;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Telegram URL Upload Controller
 * 
 * Handles uploading files from URLs directly to Telegram
 * Phase 8.2: با Progress Tracking
 * Phase 8.3: با Cloudflare Worker Fallback
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
        $rawUrl = $request->input('url');
        
        // Basic validation برای URL خام
        $validator = Validator::make(['url' => $rawUrl], [
            'url' => 'required|string|max:2048',
            'name' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1024',
            'parent_id' => 'nullable|integer|exists:file_entries,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(),[], 422);
        }

        Log::info('Upload single file from URL', [
            'original_url' => $rawUrl,
        ]);

        try {
            // استفاده از uploadWithProgress - خود سرویس باید URL را مدیریت کند
            $result = $this->uploadWithProgress->uploadFromUrl(
                $rawUrl, // استفاده از URL اصلی
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
        $rawUrls = $request->input('urls', []);
        
        // Sanitize all URLs
        $sanitizedUrls = [];
        foreach ($rawUrls as $rawUrl) {
            $sanitized = $this->sanitizeUrl($rawUrl);
            if ($sanitized) {
                $sanitizedUrls[] = $sanitized;
            }
        }
        
        $validator = Validator::make(['urls' => $sanitizedUrls], [
            'urls' => 'required|array|min:1|max:100',
            'urls.*' => 'required|url|max:2048',
            'caption' => 'nullable|string|max:1024',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(),[], 422);
        }

        Log::info('Bulk upload from URLs', [
            'original_count' => count($rawUrls),
            'sanitized_count' => count($sanitizedUrls),
        ]);

        try {
            $urls = $sanitizedUrls;
            
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
     * Validate URL before upload with Worker fallback support
     *
     * POST /api/v1/telegram/validate-url
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateUrl(Request $request): JsonResponse
    {
        $rawUrl = $request->input('url');

        // Sanitize URL
        $sanitizedUrl = $this->sanitizeUrl($rawUrl);
        if (!$sanitizedUrl) {
            return $this->error('The provided URL is not structurally valid.', [], 422);
        }

        $validator = Validator::make(['url' => $sanitizedUrl], [
            'url' => 'required|url|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), [], 422);
        }

        $url = $sanitizedUrl;
        $workerUrl = config('services.telegram.worker_url');
        $useWorker = !empty($workerUrl);

        Log::info('Validating URL', [
            'original_url' => $rawUrl,
            'sanitized_url' => $sanitizedUrl,
            'worker_enabled' => $useWorker,
            'worker_url' => $workerUrl,
        ]);

        try {
            $headers = null;
            $method = 'direct';

            // مرحله 1: اگر Worker فعال است، ابتدا از آن استفاده کنیم
            if ($useWorker) {
                try {
                    $workerTestUrl = $workerUrl . '?url=' . urlencode($url);
                    Log::info('Trying Worker validation', ['worker_url' => $workerTestUrl]);
                    
                    // استفاده از Http facade برای control بهتر
                    $response = Http::timeout(10)
                        ->withOptions(['verify' => false]) // برای SSL issues
                        ->head($workerTestUrl);

                    if ($response->successful()) {
                        $headers = $response->headers();
                        $method = 'worker';
                        Log::info('Worker validation successful');
                    } else {
                        Log::warning('Worker returned non-200', ['status' => $response->status()]);
                    }
                } catch (\Exception $workerError) {
                    Log::warning('Worker validation failed, falling back to direct', [
                        'error' => $workerError->getMessage()
                    ]);
                }
            }

            // مرحله 2: اگر Worker کار نکرد یا فعال نبود، مستقیم امتحان کنیم
            if (!$headers) {
                Log::info('Using direct validation with cURL');
                
                // استفاده از cURL برای سازگاری بهتر
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_NOBODY => true, // HEAD request
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                ]);
                
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                $curlError = curl_error($ch);
                $curlErrno = curl_errno($ch);
                curl_close($ch);
                
                if ($curlErrno !== 0 || ($httpCode < 200 || $httpCode >= 300)) {
                    Log::warning('Direct validation failed', [
                        'url' => $url,
                        'http_code' => $httpCode,
                        'curl_error' => $curlError,
                        'curl_errno' => $curlErrno,
                    ]);
                    
                    return $this->error(
                        'File is not accessible. URL may be blocked, require authentication, or the file does not exist.',
                        [
                            'url' => $url,
                            'http_code' => $httpCode,
                            'curl_error' => $curlError,
                            'method_tried' => $useWorker ? 'worker+direct' : 'direct',
                            'worker_enabled' => $useWorker,
                        ],
                        400
                    );
                }
                
                // ساخت array headers برای سازگاری با کد بعدی
                $headers = [
                    'Content-Type' => $contentType ?: 'unknown',
                    'Content-Length' => $contentLength > 0 ? $contentLength : 0,
                ];
                $method = 'direct';
            }

            // استخراج اطلاعات
            if (is_array($headers)) {
                // از cURL یا get_headers
                $contentType = $headers['Content-Type'] 
                    ?? $headers['content-type'] 
                    ?? ($headers['content-type'][0] ?? null)
                    ?? ($headers['Content-Type'][0] ?? null)
                    ?? 'unknown';
                    
                $contentLength = $headers['Content-Length'] 
                    ?? $headers['content-length']
                    ?? ($headers['content-length'][0] ?? null)
                    ?? ($headers['Content-Length'][0] ?? null)
                    ?? 0;
            } else {
                // از Http response object
                $contentType = $headers['content-type'][0] ?? $headers['Content-Type'][0] ?? 'unknown';
                $contentLength = $headers['content-length'][0] ?? $headers['Content-Length'][0] ?? 0;
            }

            // پاکسازی اطلاعات
            if (is_array($contentType)) {
                $contentType = end($contentType);
            }
            if (is_array($contentLength)) {
                $contentLength = end($contentLength);
            }
            
            // حذف charset از content-type اگر وجود داشت
            if (is_string($contentType) && strpos($contentType, ';') !== false) {
                $contentType = trim(explode(';', $contentType)[0]);
            }
            
            $contentLength = (int)$contentLength;

            // Check file size
            $canUpload = \Common\Files\Telegram\TelegramFileManager::canUpload($contentLength);

            Log::info('URL validation successful', [
                'method' => $method,
                'content_type' => $contentType,
                'content_length' => $contentLength,
            ]);

            return $this->success([
                'valid' => true,
                'content_type' => $contentType,
                'content_length' => $contentLength,
                'content_length_formatted' => $this->formatBytes($contentLength),
                'can_upload' => $canUpload['can_upload'],
                'upload_method' => $canUpload['method'],
                'reason' => $canUpload['reason'],
                'validation_method' => $method, // نشان می‌دهد از کجا validate شد
                'worker_available' => $useWorker,
            ]);

        } catch (\Exception $e) {
            Log::error('URL validation exception', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);
            return $this->error(
                'Failed to validate URL: ' . $e->getMessage(),
                ['worker_enabled' => $useWorker],
                500
            );
        }
    }

    /**
     * Sanitize URL to handle special characters
     * 
     * @param string $rawUrl
     * @return string|null
     */
    protected function sanitizeUrl(string $rawUrl): ?string
    {
        $urlParts = parse_url($rawUrl);
        
        // Check if parsing failed
        if (!$urlParts || !isset($urlParts['scheme'], $urlParts['host'])) {
            return null;
        }
        
        // Encode URL components
        if (isset($urlParts['path'])) {
            $urlParts['path'] = implode('/', array_map('rawurlencode', explode('/', $urlParts['path'])));
        }
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
            $urlParts['query'] = http_build_query($queryParams);
        }
        if (isset($urlParts['fragment'])) {
            $urlParts['fragment'] = rawurlencode($urlParts['fragment']);
        }
        
        // Reconstruct URL
        $sanitizedUrl = (isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '') .
                        (isset($urlParts['user']) ? $urlParts['user'] : '') .
                        (isset($urlParts['pass']) ? ':' . $urlParts['pass'] : '') .
                        (isset($urlParts['user']) ? '@' : '') .
                        (isset($urlParts['host']) ? $urlParts['host'] : '') .
                        (isset($urlParts['port']) ? ':' . $urlParts['port'] : '') .
                        (isset($urlParts['path']) ? $urlParts['path'] : '') .
                        (isset($urlParts['query']) ? '?' . $urlParts['query'] : '') .
                        (isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '');
        
        return $sanitizedUrl;
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
