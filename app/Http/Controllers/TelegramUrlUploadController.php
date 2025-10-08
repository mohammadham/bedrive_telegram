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
    $rawUrl = $request->input('url');

    // --- مرحله جدید: پاک‌سازی URL ---
    // ابتدا URL را به اجزای اصلی آن تجزیه می‌کنیم
    $urlParts = parse_url($rawUrl);

    // اگر URL تجزیه نشد، یعنی از ابتدا ساختار اشتباهی داشته است
    if (!$urlParts || !isset($urlParts['scheme'], $urlParts['host'])) {
        return $this->error('The provided URL is not structurally valid.', [], 422);
    }

    // مسیر (path)، کوئری (query) و فرگمنت (fragment) را Encode می‌کنیم
    // تا کاراکترهای غیرمجاز به فرمت استاندارد درآیند
    if (isset($urlParts['path'])) {
        $urlParts['path'] = rawurlencode($urlParts['path']);
    }
    if (isset($urlParts['query'])) {
        $urlParts['query'] = rawurlencode($urlParts['query']);
    }
    if (isset($urlParts['fragment'])) {
        $urlParts['fragment'] = rawurlencode($urlParts['fragment']);
    }
    
    // URL را دوباره از اجزای پاک‌سازی شده می‌سازیم
    $sanitizedUrl = (isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '') .
                    (isset($urlParts['user']) ? $urlParts['user'] : '') .
                    (isset($urlParts['pass']) ? ':' . $urlParts['pass'] : '') .
                    (isset($urlParts['user']) ? '@' : '') .
                    (isset($urlParts['host']) ? $urlParts['host'] : '') .
                    (isset($urlParts['port']) ? ':' . $urlParts['port'] : '') .
                    (isset($urlParts['path']) ? str_replace('%2F', '/', $urlParts['path']) : '') .
                    (isset($urlParts['query']) ? '?' . str_replace('%3D', '=', $urlParts['query']) : '') .
                    (isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '');

    // --- ادامه منطق قبلی با URL پاک‌سازی شده ---
    Log::info('Validating URL', [
        'original_url' => $rawUrl,
        'sanitized_url' => $sanitizedUrl,
    ]);

    // حالا URL پاک‌سازی شده را با قانون استاندارد 'url' اعتبارسنجی می‌کنیم
    $validator = Validator::make(['url' => $sanitizedUrl], [
        'url' => 'required|url|max:2048',
    ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), [], 422);
        }

        $url = $request->input('url');
        $workerUrl = config('services.telegram.worker_url');
        $useWorker = !empty($workerUrl);

        Log::info('Validating URL', [
            'url' => $url,
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
                Log::info('Using direct validation');
                $headers = @get_headers($url, 1);
                
                if (!$headers || !str_contains($headers[0], '200')) {
                    return $this->error(
                        'File is not accessible. URL may be blocked, require authentication, or the file does not exist.',
                        [
                            'url' => $url,
                            'method_tried' => $useWorker ? 'worker+direct' : 'direct',
                            'worker_enabled' => $useWorker,
                        ],
                        400
                    );
                }
                $method = 'direct';
            }

            // استخراج اطلاعات
            if (is_array($headers) && isset($headers[0])) {
                // از get_headers
                $contentType = $headers['Content-Type'] ?? 'unknown';
                $contentLength = $headers['Content-Length'] ?? 0;
            } else {
                // از Http response
                $contentType = $headers['content-type'][0] ?? $headers['Content-Type'][0] ?? 'unknown';
                $contentLength = $headers['content-length'][0] ?? $headers['Content-Length'][0] ?? 0;
            }

            if (is_array($contentType)) {
                $contentType = end($contentType);
            }
            if (is_array($contentLength)) {
                $contentLength = end($contentLength);
            }

            // Check file size
            $canUpload = \Common\Files\Telegram\TelegramFileManager::canUpload((int) $contentLength);

            Log::info('URL validation successful', [
                'method' => $method,
                'content_type' => $contentType,
                'content_length' => $contentLength,
            ]);

            return $this->success([
                'valid' => true,
                'content_type' => $contentType,
                'content_length' => (int) $contentLength,
                'content_length_formatted' => $this->formatBytes((int) $contentLength),
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
